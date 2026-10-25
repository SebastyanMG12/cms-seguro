<?php

namespace App\Services;

use App\Models\EmergencyEpisode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EpisodeStateMachine
{
    protected PhysicalResourceService $physicalResourceService;

    public function __construct(PhysicalResourceService $physicalResourceService)
    {
        $this->physicalResourceService = $physicalResourceService;
    }

    /**
     * Registra la llegada de un paciente al hospital (de 'preregistrado' a 'en_espera').
     */
    public function registerArrival(EmergencyEpisode $episode): EmergencyEpisode
    {
        return DB::transaction(function () use ($episode) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'preregistrado') {
                throw ValidationException::withMessages([
                    'status' => "No se puede registrar la llegada de un episodio en estado '{$locked->status}'.",
                ]);
            }

            $locked->arrived_at = now();
            $locked->status = 'en_espera';
            $locked->save();

            Audit::log('episodio.llegada_registrada', $locked, [
                'public_code' => $locked->public_code,
                'arrived_at' => $locked->arrived_at->toISOString(),
            ]);

            return $locked;
        });
    }

    /**
     * Asigna o actualiza la clasificación de Triage (I a V) por profesional calificado.
     * Transiciona de 'en_espera' a 'en_valoracion'.
     */
    public function assignTriage(EmergencyEpisode $episode, string $triageLevel, User $user): EmergencyEpisode
    {
        return DB::transaction(function () use ($episode, $triageLevel, $user) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['en_espera', 'en_valoracion'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'El triage solo puede asignarse a episodios en espera o en valoración.',
                ]);
            }

            if (! in_array($triageLevel, ['I', 'II', 'III', 'IV', 'V'], true)) {
                throw ValidationException::withMessages([
                    'triage_level' => 'El nivel de triage debe ser uno de los cinco niveles oficiales (I, II, III, IV, V).',
                ]);
            }

            $locked->triage_level = $triageLevel;
            $locked->triage_user_id = $user->id;
            $locked->triage_at = now();
            $locked->status = 'en_valoracion';
            $locked->save();

            Audit::log('episodio.triage_asignado', $locked, [
                'public_code' => $locked->public_code,
                'triage_level' => $triageLevel,
                'professional_id' => $user->id,
            ]);

            return $locked;
        });
    }

    /**
     * Confirma el ingreso médico formal (de 'en_valoracion' a 'ingresado').
     */
    public function confirmAdmission(EmergencyEpisode $episode): EmergencyEpisode
    {
        return DB::transaction(function () use ($episode) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'en_valoracion') {
                throw ValidationException::withMessages([
                    'status' => "Para ingresar a atención, el episodio debe haber completado el triage (estado 'en_valoracion').",
                ]);
            }

            if (empty($locked->triage_level)) {
                throw ValidationException::withMessages([
                    'triage_level' => 'No se puede confirmar el ingreso sin clasificar previamente el nivel de triage.',
                ]);
            }

            $now = now();
            if ($locked->arrived_at && $now->lt($locked->arrived_at)) {
                throw ValidationException::withMessages([
                    'admitted_at' => 'La fecha de atención médica no puede ser anterior a la hora de llegada.',
                ]);
            }

            $locked->admitted_at = $now;
            $locked->status = 'ingresado';
            $locked->save();

            Audit::log('episodio.ingreso_confirmado', $locked, [
                'public_code' => $locked->public_code,
                'admitted_at' => $locked->admitted_at->toISOString(),
            ]);

            return $locked;
        });
    }

    /**
     * Registra el egreso y cierra formalmente el episodio.
     */
    public function discharge(EmergencyEpisode $episode, string $dischargeType, ?string $notes = null): EmergencyEpisode
    {
        return DB::transaction(function () use ($episode, $dischargeType) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'ingresado') {
                throw ValidationException::withMessages([
                    'status' => 'Solo se puede registrar el egreso de pacientes que se encuentren formalmente ingresados.',
                ]);
            }

            $validDischarges = ['alta_medica', 'remision', 'alta_voluntaria', 'abandono', 'fallecimiento'];
            if (! in_array($dischargeType, $validDischarges, true)) {
                throw ValidationException::withMessages([
                    'discharge_type' => 'Tipo de egreso no válido según los protocolos hospitalarios.',
                ]);
            }

            $now = now();
            if ($locked->admitted_at && $now->lt($locked->admitted_at)) {
                throw ValidationException::withMessages([
                    'discharged_at' => 'La hora de salida no puede ser anterior a la hora de atención.',
                ]);
            }

            $locked->discharged_at = $now;
            $locked->closed_at = $now;
            $locked->discharge_type = $dischargeType;
            $locked->status = 'egresado';
            $locked->save();

            // 1. Liberar camilla activa si existía asignación
            $activeBedAssignment = $locked->activeBedAssignment;
            if ($activeBedAssignment) {
                $this->physicalResourceService->releaseBed($activeBedAssignment->bed);
                // La camilla pasa a 'en_limpieza' según protocolo de bioseguridad
                $activeBedAssignment->bed->update(['status' => 'en_limpieza']);
            }

            // 2. Finalizar asignaciones activas de personal
            $locked->activeAssignments()->update(['ended_at' => $now]);

            Audit::log('episodio.egresado', $locked, [
                'public_code' => $locked->public_code,
                'discharge_type' => $dischargeType,
                'closed_at' => $locked->closed_at->toISOString(),
            ]);

            return $locked;
        });
    }
}
