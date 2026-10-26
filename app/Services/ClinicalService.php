<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\ClinicalEntry;
use App\Models\Companion;
use App\Models\EmergencyEpisode;
use App\Models\EpisodeAssignment;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClinicalService
{
    protected PhysicalResourceService $physicalResourceService;

    public function __construct(PhysicalResourceService $physicalResourceService)
    {
        $this->physicalResourceService = $physicalResourceService;
    }

    /**
     * Agrega una entrada clínica inmutable (diagnóstico, procedimiento, nota, aclaración, anulación).
     */
    public function addClinicalEntry(
        EmergencyEpisode $episode,
        User $author,
        string $type,
        string $content,
        ?int $referencesEntryId = null,
        ?string $reason = null
    ): ClinicalEntry {
        return DB::transaction(function () use ($episode, $author, $type, $content, $referencesEntryId, $reason) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            $validTypes = ['diagnostico', 'procedimiento', 'nota', 'aclaracion', 'anulacion'];
            if (! in_array($type, $validTypes, true)) {
                throw ValidationException::withMessages([
                    'type' => 'Tipo de entrada clínica no válido.',
                ]);
            }

            // Regla sección 7.3: Con egresado, el episodio queda cerrado.
            // La única excepción es una nota aclaratoria posterior con motivo.
            if ($locked->isClosed() && $type !== 'aclaracion') {
                throw ValidationException::withMessages([
                    'type' => 'El episodio está cerrado. Solo se admiten notas aclaratorias posteriores con motivo justificado.',
                ]);
            }

            // Aclaraciones y anulaciones exigen referencia a la entrada previa y motivo
            if (in_array($type, ['aclaracion', 'anulacion'], true)) {
                if (empty($referencesEntryId)) {
                    throw ValidationException::withMessages([
                        'references_entry_id' => 'Debe indicar la entrada clínica que desea aclarar o anular.',
                    ]);
                }
                if (empty($reason) || strlen(trim($reason)) < 5) {
                    throw ValidationException::withMessages([
                        'reason' => 'El motivo de la aclaración o anulación es obligatorio (mínimo 5 caracteres).',
                    ]);
                }

                $referenced = ClinicalEntry::where('id', $referencesEntryId)
                    ->where('episode_id', $locked->id)
                    ->first();

                if (! $referenced) {
                    throw ValidationException::withMessages([
                        'references_entry_id' => 'La entrada clínica de referencia no pertenece a este episodio.',
                    ]);
                }

                if ($type === 'anulacion' && $referenced->isAnnulled()) {
                    throw ValidationException::withMessages([
                        'references_entry_id' => 'La entrada clínica seleccionada ya se encuentra anulada.',
                    ]);
                }
            }

            $entry = ClinicalEntry::create([
                'episode_id' => $locked->id,
                'type' => $type,
                'content' => $content,
                'author_user_id' => $author->id,
                'performed_at' => now(), // El servidor define la hora oficial inalterable
                'references_entry_id' => $referencesEntryId,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            Audit::log('historia.entrada_creada', $entry, [
                'episode_id' => $locked->id,
                'public_code' => $locked->public_code,
                'type' => $type,
                'references_entry_id' => $referencesEntryId,
            ]);

            return $entry;
        });
    }

    /**
     * Asigna un profesional al episodio (Médico Responsable o Personal de Apoyo).
     */
    public function assignStaff(EmergencyEpisode $episode, User $staffUser, string $role = 'responsable'): EpisodeAssignment
    {
        return DB::transaction(function () use ($episode, $staffUser, $role) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                throw ValidationException::withMessages([
                    'staff' => 'No se puede asignar personal a un episodio cerrado.',
                ]);
            }

            $profile = $staffUser->staffProfile;
            if (! $profile || ! $profile->isAssignable()) {
                throw ValidationException::withMessages([
                    'staff' => 'El profesional seleccionado no está disponible (en descanso, inactivo o sin perfil asistencial).',
                ]);
            }

            // Validar turno activo (sección 7.3)
            $hasActiveShift = Shift::where('user_id', $staffUser->id)
                ->where('status', 'activo')
                ->exists();

            if (! $hasActiveShift) {
                throw ValidationException::withMessages([
                    'staff' => 'El profesional debe encontrarse en un turno activo para ser asignado.',
                ]);
            }

            $now = now();

            if ($role === 'responsable') {
                if ($locked->responsible_user_id === $staffUser->id) {
                    throw ValidationException::withMessages([
                        'staff' => "El profesional {$staffUser->name} ya se encuentra asignado como médico responsable de este episodio.",
                    ]);
                }

                // Finalizar asignación de responsable anterior si existe
                EpisodeAssignment::where('episode_id', $locked->id)
                    ->where('role', 'responsable')
                    ->whereNull('ended_at')
                    ->update(['ended_at' => $now]);

                // Si este mismo usuario estaba asignado previamente como apoyo, finalizar esa asignación
                EpisodeAssignment::where('episode_id', $locked->id)
                    ->where('user_id', $staffUser->id)
                    ->where('role', 'apoyo')
                    ->whereNull('ended_at')
                    ->update(['ended_at' => $now]);

                $locked->responsible_user_id = $staffUser->id;
                $locked->save();
            } else {
                // Si el profesional ya es el médico responsable, no puede asignarse simultáneamente como apoyo
                if ($locked->responsible_user_id === $staffUser->id) {
                    throw ValidationException::withMessages([
                        'staff' => "El profesional {$staffUser->name} ya es el médico responsable de este episodio y no puede asignarse simultáneamente como apoyo.",
                    ]);
                }

                // Si ya está asignado activamente como apoyo, evitar duplicados
                $existing = EpisodeAssignment::where('episode_id', $locked->id)
                    ->where('user_id', $staffUser->id)
                    ->where('role', 'apoyo')
                    ->whereNull('ended_at')
                    ->first();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'staff' => "El profesional {$staffUser->name} ya se encuentra asignado como personal de apoyo en este episodio.",
                    ]);
                }
            }

            $assignment = EpisodeAssignment::create([
                'episode_id' => $locked->id,
                'user_id' => $staffUser->id,
                'role' => $role,
                'started_at' => $now,
            ]);

            Audit::log('episodio.personal_asignado', $locked, [
                'public_code' => $locked->public_code,
                'professional_id' => $staffUser->id,
                'role' => $role,
            ]);

            return $assignment;
        });
    }

    /**
     * Asigna una camilla al episodio (exige que el episodio esté en estado 'ingresado').
     */
    public function assignBedToEpisode(EmergencyEpisode $episode, Bed $bed): BedAssignment
    {
        return DB::transaction(function () use ($episode, $bed) {
            $lockedEpisode = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            if ($lockedEpisode->status !== 'ingresado') {
                throw ValidationException::withMessages([
                    'bed' => "No se puede asignar camilla a un episodio en estado '{$lockedEpisode->status}'. El paciente debe estar ingresado.",
                ]);
            }

            // Si ya posee una camilla asignada activa, liberarla primero
            $currentBedAssignment = $lockedEpisode->activeBedAssignment;
            if ($currentBedAssignment) {
                $this->physicalResourceService->releaseBed($currentBedAssignment->bed);
            }

            $assignment = $this->physicalResourceService->assignBed($bed, $lockedEpisode->id);

            Audit::log('episodio.camilla_asignada', $lockedEpisode, [
                'public_code' => $lockedEpisode->public_code,
                'bed_code' => $bed->code,
            ]);

            return $assignment;
        });
    }

    /**
     * Contador de pacientes activos asignados por profesional (calculado, sección 7.5).
     */
    public function countActivePatients(User $staffUser): int
    {
        return EpisodeAssignment::where('user_id', $staffUser->id)
            ->whereNull('ended_at')
            ->whereHas('episode', function ($q) {
                $q->whereNotIn('status', ['egresado', 'expirado']);
            })
            ->distinct('episode_id')
            ->count('episode_id');
    }

    /**
     * Auditoría de lectura de historia clínica y acceso excepcional.
     */
    public function recordMedicalRecordRead(EmergencyEpisode $episode, User $user, ?string $exceptionalReason = null): void
    {
        $isAssigned = ($episode->responsible_user_id === $user->id)
            || $episode->activeAssignments()->where('user_id', $user->id)->exists();

        $metadata = [
            'public_code' => $episode->public_code,
            'is_assigned' => $isAssigned,
        ];

        if (! empty($exceptionalReason)) {
            $metadata['exceptional_reason'] = $exceptionalReason;
            Audit::log('historia.acceso_excepcional', $episode, $metadata, $user->id);
        } else {
            Audit::log('historia.lectura', $episode, $metadata, $user->id);
        }
    }

    /**
     * Actualiza o crea datos del acompañante mientras el episodio esté abierto.
     */
    public function updateCompanion(EmergencyEpisode $episode, array $data): Companion
    {
        return DB::transaction(function () use ($episode, $data) {
            $locked = EmergencyEpisode::where('id', $episode->id)->lockForUpdate()->firstOrFail();

            if ($locked->isClosed()) {
                throw ValidationException::withMessages([
                    'companion' => 'No se pueden modificar datos del acompañante en un episodio cerrado.',
                ]);
            }

            $companion = Companion::firstOrNew(['episode_id' => $locked->id]);
            $companion->name = $data['name'] ?? $companion->name;
            $companion->phone = $data['phone'] ?? $companion->phone;
            $companion->relationship = $data['relationship'] ?? $companion->relationship;
            $companion->save();

            Audit::log('episodio.acompanante_actualizado', $locked, [
                'public_code' => $locked->public_code,
                'has_companion' => ! empty($companion->name),
            ]);

            return $companion;
        });
    }
}
