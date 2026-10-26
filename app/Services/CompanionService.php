<?php

namespace App\Services;

use App\Models\Companion;
use App\Models\Consent;
use App\Models\EmergencyEpisode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CompanionService
{
    /**
     * Genera, asigna o regenera un PIN presencial para el acompañante.
     * Guarda el PIN cifrado para que el personal autorizado pueda consultarlo y su hash para validación rápida.
     */
    public function generatePin(EmergencyEpisode $episode, User $staffUser, ?string $customPin = null): string
    {
        return DB::transaction(function () use ($episode, $staffUser, $customPin) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                throw ValidationException::withMessages([
                    'companion' => 'No se puede emitir un PIN para un episodio cerrado.',
                ]);
            }

            // Si se proporciona un PIN personalizado (o los dígitos del documento), se utiliza; de lo contrario se genera uno de 6 dígitos
            $plainPin = $customPin ? trim($customPin) : sprintf('%06d', random_int(100000, 999999));

            $companion = Companion::firstOrNew(['episode_id' => $locked->id]);
            $companion->pin = $plainPin;
            $companion->pin_hash = Hash::make($plainPin);
            $companion->verified_by_user_id = $staffUser->id;
            $companion->verified_at = now();
            $companion->revoked_at = null; // Si estaba revocado, la emisión de nuevo PIN lo reactiva
            $companion->save();

            Audit::log('acompanante.pin_generado', $locked, [
                'public_code' => $locked->public_code,
                'companion_id' => $companion->id,
                'verified_by_user_id' => $staffUser->id,
            ]);

            return $plainPin;
        });
    }

    /**
     * Restablece el PIN del acompañante a los últimos dígitos del documento del paciente (o código NN).
     */
    public function resetPinToDefault(EmergencyEpisode $episode, User $staffUser): string
    {
        return DB::transaction(function () use ($episode, $staffUser) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();
            $defaultPin = $locked->getDefaultCompanionPin();

            $companion = Companion::firstOrNew(['episode_id' => $locked->id]);
            $companion->pin = null;
            $companion->pin_hash = Hash::make($defaultPin);
            $companion->verified_by_user_id = $staffUser->id;
            $companion->verified_at = now();
            $companion->revoked_at = null;
            $companion->save();

            Audit::log('acompanante.pin_restablecido_defecto', $locked, [
                'public_code' => $locked->public_code,
                'companion_id' => $companion->id,
                'verified_by_user_id' => $staffUser->id,
            ]);

            return $defaultPin;
        });
    }

    /**
     * Revoca el acceso del acompañante por razones de custodia o solicitud del paciente.
     */
    public function revokeAccess(EmergencyEpisode $episode, User $staffUser, ?string $reason = null): void
    {
        DB::transaction(function () use ($episode, $staffUser, $reason) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            $companion = Companion::where('episode_id', $locked->id)->first();
            if (! $companion || $companion->pin_hash === null) {
                throw ValidationException::withMessages([
                    'companion' => 'El episodio no cuenta con un acceso de acompañante activo para revocar.',
                ]);
            }

            $companion->revoked_at = now();
            $companion->save();

            Audit::log('acompanante.acceso_revocado', $locked, [
                'public_code' => $locked->public_code,
                'companion_id' => $companion->id,
                'revoked_by_user_id' => $staffUser->id,
                'reason' => $reason ?? 'Revocación de acceso de acompañante',
            ]);
        });
    }

    /**
     * Otorga o revoca el consentimiento del paciente para compartir información clínica con el acompañante.
     */
    public function toggleClinicalConsent(EmergencyEpisode $episode, User $staffUser, bool $enable, ?string $grantedBy = 'paciente', ?string $relationship = null): void
    {
        DB::transaction(function () use ($episode, $staffUser, $enable, $grantedBy, $relationship) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            $activeConsent = $locked->consents()
                ->where('type', 'compartir_acompanante')
                ->whereNull('revoked_at')
                ->latest('id')
                ->first();

            if ($enable) {
                if ($activeConsent) {
                    return; // Ya se encuentra activo
                }

                Consent::create([
                    'episode_id' => $locked->id,
                    'type' => 'compartir_acompanante',
                    'granted_by' => $grantedBy ?? 'paciente',
                    'granted_by_relationship' => $relationship ?? ($grantedBy === 'paciente' ? 'titular' : 'representante'),
                    'scope' => [
                        'finalidad' => 'informacion_estado_acompanante',
                        'procedimientos' => true,
                        'diagnosticos' => true,
                        'ley' => '1581_de_2012',
                    ],
                    'text_version' => 'v1.0',
                    'captured_by_user_id' => $staffUser->id,
                    'granted_at' => now(),
                    'created_at' => now(),
                ]);

                Audit::log('consentimiento.otorgado', $locked, [
                    'public_code' => $locked->public_code,
                    'type' => 'compartir_acompanante',
                    'captured_by_user_id' => $staffUser->id,
                ]);
            } else {
                if ($activeConsent) {
                    $activeConsent->update(['revoked_at' => now()]);

                    Audit::log('consentimiento.revocado', $locked, [
                        'public_code' => $locked->public_code,
                        'type' => 'compartir_acompanante',
                        'revoked_by_user_id' => $staffUser->id,
                    ]);
                }
            }
        });
    }

    /**
     * Valida el código público y el PIN de acceso entregado presencialmente.
     * Retorna array con 'success' => bool y el 'status' o 'episode'.
     */
    public function verifyAccess(string $publicCode, string $pin): array
    {
        $code = strtoupper(trim($publicCode));

        $episode = EmergencyEpisode::where('public_code', $code)
            ->with([
                'patient',
                'companion',
                'consents',
                'activeBedAssignment.bed.room.area',
                'clinicalEntries' => function ($q) {
                    $q->whereIn('type', ['diagnostico', 'procedimiento'])->orderBy('performed_at', 'asc');
                },
            ])
            ->first();

        if (! $episode) {
            return [
                'success' => false,
                'status' => 'invalid_credentials',
            ];
        }

        if ($episode->companion && $episode->companion->revoked_at !== null) {
            return [
                'success' => false,
                'status' => 'revoked',
                'episode' => $episode,
            ];
        }

        // Validación de coincidencia de PIN:
        // 1. PIN directo guardado (cifrado)
        // 2. Hash del PIN guardado
        // 3. Coincidencia con el PIN predeterminado (últimos 4 dígitos del documento o código NN)
        $isMatch = false;

        if ($episode->companion && ! empty($episode->companion->pin)) {
            $isMatch = ((string) $episode->companion->pin === (string) $pin);
        }

        if (! $isMatch && $episode->companion && ! empty($episode->companion->pin_hash)) {
            $isMatch = Hash::check($pin, $episode->companion->pin_hash);
        }

        if (! $isMatch && (! $episode->companion || empty($episode->companion->pin_hash))) {
            $defaultPin = $episode->getDefaultCompanionPin();
            if ((string) $pin === (string) $defaultPin) {
                $isMatch = true;
                // Auto-inicializar registro de acompañante con el PIN por defecto
                $comp = Companion::firstOrNew(['episode_id' => $episode->id]);
                $comp->pin = $pin;
                $comp->pin_hash = Hash::make($pin);
                $comp->save();
            }
        }

        if (! $isMatch) {
            return [
                'success' => false,
                'status' => 'invalid_credentials',
            ];
        }

        if ($episode->isCompanionAccessExpired()) {
            return [
                'success' => false,
                'status' => 'expired',
                'episode' => $episode,
            ];
        }

        return [
            'success' => true,
            'status' => 'active',
            'episode' => $episode,
        ];
    }
}
