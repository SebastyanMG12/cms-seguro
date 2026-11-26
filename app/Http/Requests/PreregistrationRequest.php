<?php

namespace App\Http\Requests;

use App\Rules\PersonName;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreregistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('full_name')) {
            $this->merge([
                'full_name' => preg_replace('/\s+/', ' ', trim((string) $this->input('full_name'))),
            ]);
        }

        if ($this->has('companion_name')) {
            $companionName = trim((string) $this->input('companion_name'));
            $this->merge([
                'companion_name' => $companionName !== '' ? preg_replace('/\s+/', ' ', $companionName) : null,
            ]);
        }
    }

    public function rules(): array
    {
        $allowedDocTypes = [
            'Cédula de ciudadanía',
            'Tarjeta de identidad',
            'Registro civil',
            'Cédula de extranjería',
            'Pasaporte',
            'Permiso especial',
            'Menor sin identificación',
        ];

        $allowedBloodTypes = [
            'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'No sé',
        ];

        $birthDateInput = $this->input('birth_date');
        $approximateAge = $this->input('approximate_age');
        $calculatedAge = null;

        if (! empty($birthDateInput)) {
            try {
                $nowBogota = Carbon::now('America/Bogota');
                $calculatedAge = Carbon::parse($birthDateInput, 'America/Bogota')->diffInYears($nowBogota);
            } catch (\Throwable) {
                $calculatedAge = null;
            }
        } elseif ($approximateAge !== null && $approximateAge !== '') {
            $calculatedAge = (int) $approximateAge;
        }

        $isMinor = ($calculatedAge !== null && $calculatedAge < 18) || in_array($this->input('document_type'), [
            'Tarjeta de identidad',
            'Registro civil',
            'Menor sin identificación',
        ], true);

        $hasCompanionData = ! empty($this->input('companion_name'))
            || ! empty($this->input('companion_phone'))
            || ! empty($this->input('companion_relationship'));

        $companionRequired = $isMinor || $hasCompanionData;

        return [
            // Paciente
            'full_name' => ['required', 'string', new PersonName(2, 150)],
            'document_type' => ['nullable', 'string', Rule::in($allowedDocTypes)],
            'document_number' => [
                Rule::requiredIf(function () {
                    $docType = $this->input('document_type');

                    return ! empty($docType) && $docType !== 'Menor sin identificación';
                }),
                'nullable',
                'string',
                'min:3',
                'max:20',
                'regex:/^[A-Za-z0-9\-\.]+$/',
            ],
            'birth_date' => ['nullable', 'date'],
            'approximate_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'gender' => ['nullable', 'string', Rule::in(['masculino', 'femenino', 'otro'])],
            'patient_phone' => ['nullable', 'string', 'min:7', 'max:15', 'regex:/^[0-9\+\-\s]+$/'],

            // Motivo y clínicos
            'emergency_reason' => ['required', 'string', 'min:5', 'max:500'],
            'blood_type' => ['nullable', 'string', Rule::in($allowedBloodTypes)],
            'medication_allergies' => ['required', 'string', Rule::in(['ninguna', 'no_se', 'si'])],
            'allergies_detail' => [
                Rule::requiredIf($this->input('medication_allergies') === 'si'),
                'nullable',
                'string',
                'min:2',
                'max:500',
            ],
            'additional_notes' => ['nullable', 'string', 'max:1000'],
            'warning_signs' => ['nullable', 'array'],
            'warning_signs.*' => ['string', 'max:100'],

            // Acompañante
            'companion_name' => [
                $companionRequired ? 'required' : 'nullable',
                'string',
                new PersonName(2, 150),
            ],
            'companion_phone' => [
                $companionRequired ? 'required' : 'nullable',
                'string',
                'min:7',
                'max:15',
                'regex:/^[0-9\+\-\s]+$/',
            ],
            'companion_relationship' => [
                $companionRequired ? 'required' : 'nullable',
                'string',
                'min:2',
                'max:50',
            ],

            // Consentimientos y seguridad
            'consent_data_treatment' => ['accepted'],
            'consent_share_companion' => ['nullable', 'boolean'],
            'hospital_contact_time' => ['nullable', 'string', 'max:0'], // Honeypot: debe estar vacío
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Anti-tamper de triage
            if ($this->has('triage_level')) {
                $validator->errors()->add('triage_level', 'El nivel de triage no puede ser diligenciado por el paciente.');
            }

            // Al menos un teléfono de contacto
            if (empty($this->input('patient_phone')) && empty($this->input('companion_phone'))) {
                $validator->errors()->add('patient_phone', 'Es obligatorio registrar al menos un número de teléfono de contacto (del paciente o del acompañante).');
            }

            $docType = $this->input('document_type');
            $birthDateInput = $this->input('birth_date');
            $approximateAge = $this->input('approximate_age');
            $age = null;

            if (! empty($birthDateInput)) {
                try {
                    $birthDate = Carbon::parse($birthDateInput, 'America/Bogota');
                    $nowBogota = Carbon::now('America/Bogota');

                    if ($birthDate->greaterThan($nowBogota)) {
                        $validator->errors()->add('birth_date', 'La fecha de nacimiento no puede ser una fecha futura.');

                        return;
                    }

                    if ($birthDate->diffInYears($nowBogota) > 120) {
                        $validator->errors()->add('birth_date', 'La fecha de nacimiento no puede ser anterior a 120 años.');

                        return;
                    }

                    $age = (int) $birthDate->diffInYears($nowBogota);
                } catch (\Throwable) {
                    $validator->errors()->add('birth_date', 'El formato de fecha de nacimiento es inválido.');

                    return;
                }
            } elseif ($approximateAge !== null && $approximateAge !== '') {
                $age = (int) $approximateAge;
            }

            // Validar coherencia documental según normativa colombiana (Registraduría Nacional y Migración Colombia)
            if ($age !== null && ! empty($docType)) {
                if ($docType === 'Cédula de ciudadanía' && $age < 18) {
                    $validator->errors()->add('document_type', 'La Cédula de ciudadanía sólo es válida para personas mayores de 18 años (edad calculada: '.$age.' años).');
                }

                if ($docType === 'Tarjeta de identidad' && ($age < 7 || $age >= 18)) {
                    $validator->errors()->add('document_type', 'La Tarjeta de identidad sólo aplica para menores entre 7 y 17 años cumplidos (edad calculada: '.$age.' años).');
                }

                if ($docType === 'Registro civil' && $age >= 18) {
                    $validator->errors()->add('document_type', 'El Registro civil aplica para menores de edad; los mayores de 18 años deben identificarse con Cédula de ciudadanía o extranjería.');
                }

                if ($docType === 'Menor sin identificación' && $age >= 18) {
                    $validator->errors()->add('document_type', 'La categoría de menor sin identificación sólo aplica para personas menores de 18 años.');
                }

                if ($docType === 'Cédula de extranjería' && $age < 7) {
                    $validator->errors()->add('document_type', 'La Cédula de extranjería sólo se expide a extranjeros a partir de los 7 años de edad.');
                }
            }

            // Exigencia de acompañante por edad calculada (< 18 años) o por tipo de documento de menor
            $isMinor = ($age !== null && $age < 18) || in_array($docType, [
                'Tarjeta de identidad',
                'Registro civil',
                'Menor sin identificación',
            ], true);

            if ($isMinor && empty($this->input('companion_name'))) {
                $validator->errors()->add('companion_name', 'Para pacientes menores de 18 años es obligatorio registrar los datos del acompañante o representante legal.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'El nombre completo del paciente es obligatorio.',
            'emergency_reason.required' => 'El motivo de consulta de urgencia es obligatorio.',
            'emergency_reason.max' => 'El motivo de urgencia no puede exceder 500 caracteres.',
            'document_number.required' => 'El número de documento es obligatorio cuando se especifica un tipo de documento.',
            'document_number.regex' => 'El formato del número de documento es inválido.',
            'medication_allergies.required' => 'Debe indicar si presenta o no alergias a medicamentos.',
            'allergies_detail.required' => 'Debe detallar a qué medicamentos es alérgico.',
            'consent_data_treatment.accepted' => 'Debe aceptar la autorización de tratamiento de datos personales conforme a la Ley 1581 de 2012 para continuar.',
            'hospital_contact_time.max' => 'Solicitud descartada por detección de formulario automatizado.',
        ];
    }
}
