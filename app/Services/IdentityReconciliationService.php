<?php

namespace App\Services;

use App\Models\Consent;
use App\Models\EmergencyEpisode;
use App\Models\EpisodeClinicalData;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdentityReconciliationService
{
    /**
     * Registra el ingreso de emergencia de un paciente sin identificar (NN).
     * Amparado bajo excepción de interés vital (Ley 1581 Art. 10).
     */
    public function registerEmergencyNn(array $data, User $author): EmergencyEpisode
    {
        return DB::transaction(function () use ($data, $author): EmergencyEpisode {
            $nnCode = EmergencyEpisode::generateNnCode();
            $publicCode = EmergencyEpisode::generatePublicCode();

            // 1. Crear registro inicial de paciente NN (identidad no identificada)
            $patient = Patient::create([
                'full_name' => "Paciente No Identificado ({$nnCode})",
                'identity_status' => 'no_identificado',
                'gender' => $data['apparent_sex'] ?? 'indeterminado',
                'document_type' => null,
                'document_number' => null,
                'document_index' => null,
                'birth_date' => null,
                'phone' => null,
            ]);

            // 2. Crear episodio de emergencia NN directamente en sala de espera (arrived_at = now())
            $episode = EmergencyEpisode::create([
                'patient_id' => $patient->id,
                'original_nn_patient_id' => $patient->id,
                'public_code' => $publicCode,
                'nn_code' => $nnCode,
                'is_nn' => true,
                'apparent_sex' => $data['apparent_sex'],
                'estimated_age' => $data['estimated_age'],
                'distinguishing_marks' => $data['distinguishing_marks'],
                'arrival_mode' => $data['arrival_mode'],
                'brought_by_name' => $data['brought_by_name'],
                'brought_by_contact' => $data['brought_by_contact'] ?? null,
                'no_consent_reason' => $data['no_consent_reason'] ?? 'interés vital, paciente sin capacidad',
                'origin' => 'urgencias_nn',
                'status' => 'en_espera',
                'arrived_at' => now(),
                'reason' => $data['reason'],
            ]);

            // 3. Crear datos clínicos iniciales (alergias y tipo de sangre desconocidos)
            EpisodeClinicalData::create([
                'episode_id' => $episode->id,
                'allergies_type' => 'no_se',
                'blood_type' => 'desconocido',
                'notes' => $data['initial_notes'] ?? null,
            ]);

            // 4. Registro formal de excepción legal de consentimiento previo (Ley 1581 de 2012 Art. 10 Lit. B)
            Consent::create([
                'episode_id' => $episode->id,
                'type' => 'tratamiento_datos',
                'granted_by' => 'excepcion_legal_interes_vital',
                'scope' => [
                    'atencion_urgencia_vital' => true,
                    'motivo' => $episode->no_consent_reason,
                    'normativa' => 'Ley 1581 de 2012 Art. 10 Literal B (situación de urgencia médica o sanitaria)',
                ],
                'text_version' => 'v1.0-urgencia-nn',
                'captured_by_user_id' => $author->id,
                'granted_at' => now(),
                'ip_hash' => hash('sha256', request()->ip() ?? '127.0.0.1'),
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ]);

            Audit::log('episodio.ingreso_nn_creado', $episode, [
                'nn_code' => $nnCode,
                'public_code' => $publicCode,
                'apparent_sex' => $episode->apparent_sex,
                'arrival_mode' => $episode->arrival_mode,
                'author_id' => $author->id,
            ]);

            return $episode;
        });
    }

    /**
     * Concilia la identidad de un paciente NN vinculándolo a un paciente real identificado.
     * Preserva permanentemente el historial y registro original NN.
     */
    public function reconcileIdentity(EmergencyEpisode $episode, array $data, User $author): EmergencyEpisode
    {
        return DB::transaction(function () use ($episode, $data, $author): EmergencyEpisode {
            /** @var EmergencyEpisode $locked */
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            if (! $locked->is_nn) {
                throw ValidationException::withMessages([
                    'reconcile' => 'Solo se puede conciliar la identidad en episodios registrados como paciente sin identificar (NN).',
                ]);
            }

            if ($locked->isReconciled()) {
                throw ValidationException::withMessages([
                    'reconcile' => "La identidad de este episodio ya fue conciliada previamente el {$locked->reconciled_at->format('d/m/Y H:i')}.",
                ]);
            }

            $evidence = trim((string) ($data['reconciliation_evidence'] ?? ''));
            if (strlen($evidence) < 10) {
                throw ValidationException::withMessages([
                    'reconciliation_evidence' => 'Debe ingresar la evidencia de verificación de identidad (mínimo 10 caracteres).',
                ]);
            }

            $targetPatient = null;

            // Opción A: Vinculación con paciente existente en base de datos
            if (! empty($data['existing_patient_id'])) {
                $targetPatient = Patient::find($data['existing_patient_id']);
                if (! $targetPatient || $targetPatient->id === $locked->original_nn_patient_id) {
                    throw ValidationException::withMessages([
                        'existing_patient_id' => 'El paciente existente seleccionado no es válido o corresponde al registro temporal NN.',
                    ]);
                }
            } else {
                // Opción B: Identificación con datos de documento verificados
                $docType = $data['document_type'] ?? null;
                $docNumber = $data['document_number'] ?? null;

                if (empty($docType) || empty($docNumber)) {
                    throw ValidationException::withMessages([
                        'document_number' => 'El tipo y número de documento son obligatorios para identificar al paciente.',
                    ]);
                }

                $blindIndex = Patient::computeDocumentIndex($docType, $docNumber);

                // Búsqueda por índice ciego
                $existing = Patient::where('document_index', $blindIndex)->first();

                if ($existing) {
                    $targetPatient = $existing;
                    // Actualizar nombre o teléfono si se aportaron datos más completos
                    if (! empty($data['full_name']) && empty($targetPatient->full_name)) {
                        $targetPatient->update(['full_name' => $data['full_name']]);
                    }
                } else {
                    $fullName = trim((string) ($data['full_name'] ?? ''));
                    if (strlen($fullName) < 3) {
                        throw ValidationException::withMessages([
                            'full_name' => 'El nombre completo del paciente identificado es obligatorio (mínimo 3 caracteres).',
                        ]);
                    }

                    $targetPatient = Patient::create([
                        'full_name' => $fullName,
                        'document_type' => $docType,
                        'document_number' => $docNumber,
                        'document_index' => $blindIndex,
                        'birth_date' => $data['birth_date'] ?? null,
                        'gender' => $data['gender'] ?? $locked->apparent_sex,
                        'phone' => $data['phone'] ?? null,
                        'identity_status' => 'identificado',
                    ]);
                }
            }

            // Actualizar el episodio vinculando al paciente identificado,
            // pero PRESERVANDO el código NN histórico y el paciente NN original
            $locked->patient_id = $targetPatient->id;
            $locked->reconciled_at = now();
            $locked->reconciled_by_user_id = $author->id;
            $locked->reconciliation_evidence = $evidence;
            $locked->save();

            Audit::log('episodio.identidad_conciliada', $locked, [
                'nn_code' => $locked->nn_code,
                'public_code' => $locked->public_code,
                'original_patient_id' => $locked->original_nn_patient_id,
                'reconciled_patient_id' => $targetPatient->id,
                'author_id' => $author->id,
                'evidence' => $evidence,
            ]);

            return $locked;
        });
    }

    /**
     * Búsqueda segura de paciente existente por documento para soporte en conciliación.
     */
    public function findPatientByDocument(string $docType, string $docNumber): ?Patient
    {
        $index = Patient::computeDocumentIndex($docType, $docNumber);
        if (! $index) {
            return null;
        }

        return Patient::where('document_index', $index)->first();
    }
}
