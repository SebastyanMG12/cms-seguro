<?php

namespace App\Services;

use App\Models\Companion;
use App\Models\Consent;
use App\Models\EmergencyEpisode;
use App\Models\EpisodeClinicalData;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PreregistrationService
{
    /**
     * Procesa y registra un pre-registro público cumpliendo Ley 1581 y anti-enumeración.
     */
    public function createPreregistration(array $data, ?string $clientIp = null, ?string $userAgent = null): EmergencyEpisode
    {
        return DB::transaction(function () use ($data, $clientIp, $userAgent) {
            $docType = $data['document_type'] ?? null;
            $docNumber = $data['document_number'] ?? null;
            $isMinor = in_array($docType, [
                'Tarjeta de identidad',
                'Registro civil',
                'Menor sin identificación',
            ], true);

            // Cálculo del índice ciego para búsqueda sin revelar documento
            $documentIndex = Patient::computeDocumentIndex($docType, $docNumber);

            // Búsqueda anti-enumeración: si ya existe el paciente por índice ciego, se asocia el nuevo episodio
            $patient = null;
            if ($documentIndex !== null) {
                $patient = Patient::where('document_index', $documentIndex)->first();
            }

            if (! $patient) {
                $patient = Patient::create([
                    'full_name' => $data['full_name'],
                    'document_type' => $docType,
                    'document_number' => $docNumber,
                    'document_index' => $documentIndex,
                    'birth_date' => $data['birth_date'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'phone' => $data['patient_phone'] ?? null,
                    'identity_status' => empty($docNumber) ? 'no_identificado' : 'identificado',
                ]);
            } else {
                // Actualizar teléfono si se proporcionó uno nuevo
                if (! empty($data['patient_phone']) && empty($patient->phone)) {
                    $patient->update(['phone' => $data['patient_phone']]);
                }
            }

            $ttlHours = (int) config('hospital.preregistration_ttl_hours', 24);

            // 1. Crear Episodio de Urgencia en estado preregistrado
            $episode = EmergencyEpisode::create([
                'patient_id' => $patient->id,
                'public_code' => EmergencyEpisode::generatePublicCode(),
                'origin' => 'pre_registro',
                'status' => 'preregistrado',
                'reason' => $data['emergency_reason'],
                'pre_registration_expires_at' => now()->addHours($ttlHours),
            ]);

            // 2. Crear datos clínicos asociados al episodio
            $bloodType = $data['blood_type'] ?? 'desconocido';
            if ($bloodType === 'No sé' || empty($bloodType)) {
                $bloodType = 'desconocido';
            }

            EpisodeClinicalData::create([
                'episode_id' => $episode->id,
                'allergies_type' => $data['medication_allergies'],
                'allergies_detail' => ($data['medication_allergies'] === 'si') ? ($data['allergies_detail'] ?? null) : null,
                'blood_type' => $bloodType,
                'notes' => $data['additional_notes'] ?? null,
                'warning_signs' => $data['warning_signs'] ?? null,
            ]);

            // 3. Crear datos del acompañante si se proveyeron
            if (! empty($data['companion_name'])) {
                Companion::create([
                    'episode_id' => $episode->id,
                    'name' => $data['companion_name'],
                    'phone' => $data['companion_phone'] ?? null,
                    'relationship' => $data['companion_relationship'] ?? null,
                ]);
            }

            $ipHash = hash('sha256', $clientIp ?? request()->ip() ?? '127.0.0.1');
            $ua = Str::limit((string) ($userAgent ?? request()->userAgent()), 255, '');
            $consentVersion = (string) config('hospital.consent_version', 'v1.0');

            // 4. Consentimiento obligatorio de tratamiento de datos (Ley 1581)
            Consent::create([
                'episode_id' => $episode->id,
                'type' => 'tratamiento_datos',
                'granted_by' => $isMinor ? 'representante' : 'paciente',
                'granted_by_relationship' => $isMinor ? ($data['companion_relationship'] ?? 'representante') : 'titular',
                'scope' => [
                    'finalidad' => 'atencion_urgencias',
                    'datos_sensibles' => true,
                    'ley' => '1581_de_2012',
                ],
                'text_version' => $consentVersion,
                'granted_at' => now(),
                'ip_hash' => $ipHash,
                'user_agent' => $ua,
                'created_at' => now(),
            ]);

            // 5. Consentimiento opcional y separado para compartir con el acompañante
            if (! empty($data['consent_share_companion'])) {
                Consent::create([
                    'episode_id' => $episode->id,
                    'type' => 'compartir_acompanante',
                    'granted_by' => $isMinor ? 'representante' : 'paciente',
                    'granted_by_relationship' => $isMinor ? ($data['companion_relationship'] ?? 'representante') : 'titular',
                    'scope' => [
                        'finalidad' => 'informacion_estado_acompanante',
                        'ubicacion' => true,
                    ],
                    'text_version' => $consentVersion,
                    'granted_at' => now(),
                    'ip_hash' => $ipHash,
                    'user_agent' => $ua,
                    'created_at' => now(),
                ]);
            }

            // Registro de auditoría (sin datos sensibles ni de salud)
            Audit::log('preregistro.creado', $episode, [
                'public_code' => $episode->public_code,
                'has_companion' => ! empty($data['companion_name']),
                'has_warning_signs' => ! empty($data['warning_signs']),
            ]);

            return $episode;
        });
    }

    /**
     * Expira pre-registros vencidos según la tarea programada.
     */
    public function expireOldPreregistrations(): int
    {
        $expiredEpisodes = EmergencyEpisode::where('status', 'preregistrado')
            ->where('pre_registration_expires_at', '<', now())
            ->lockForUpdate()
            ->get();

        $count = 0;
        foreach ($expiredEpisodes as $episode) {
            $episode->update(['status' => 'expirado']);
            Audit::log('preregistro.expirado', $episode, [
                'public_code' => $episode->public_code,
            ]);
            $count++;
        }

        return $count;
    }
}
