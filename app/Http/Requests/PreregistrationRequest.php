<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreregistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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

        $isMinor = in_array($this->input('document_type'), [
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
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
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
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
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
                'min:2',
                'max:150',
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

            // Si es menor de edad, requerir explícitamente acompañante o representante
            $isMinor = in_array($this->input('document_type'), [
                'Tarjeta de identidad',
                'Registro civil',
                'Menor sin identificación',
            ], true);

            if ($isMinor && empty($this->input('companion_name'))) {
                $validator->errors()->add('companion_name', 'Para pacientes menores de edad es obligatorio registrar los datos del acompañante o representante legal.');
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
