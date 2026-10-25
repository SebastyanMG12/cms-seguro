<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffService
{
    public function generateStaffCode(string $prefix = 'MED'): string
    {
        do {
            $code = sprintf('%s-%s', strtoupper($prefix), strtoupper(Str::random(6)));
        } while (StaffProfile::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function createProfile(User $user, string $specialty, ?string $prefix = null): StaffProfile
    {
        if ($user->staffProfile()->exists()) {
            throw ValidationException::withMessages([
                'user' => 'El usuario ya cuenta con un perfil de personal asistencial.',
            ]);
        }

        return DB::transaction(function () use ($user, $specialty, $prefix): StaffProfile {
            $determinedPrefix = $prefix ?? ($user->hasRole('enfermero') ? 'ENF' : 'MED');
            $code = $this->generateStaffCode($determinedPrefix);

            $profile = StaffProfile::create([
                'user_id' => $user->id,
                'code' => $code,
                'specialty' => $specialty,
                'status' => StaffProfile::STATUS_ACTIVO,
            ]);

            Audit::log('staff_profile_created', $profile, ['code' => $code]);

            return $profile;
        });
    }

    public function createStaffWithUser(array $data, ?User $actor = null): StaffProfile
    {
        return DB::transaction(function () use ($data, $actor): StaffProfile {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
            ]);

            $roles = $data['roles'] ?? ['medico'];
            $user->syncRoles($roles);

            Audit::log('user_created', $user, ['roles' => $roles], $actor?->id);

            return $this->createProfile($user, $data['specialty']);
        });
    }

    public function updateProfile(StaffProfile $profile, array $data, ?User $actor = null): StaffProfile
    {
        return DB::transaction(function () use ($profile, $data, $actor): StaffProfile {
            $oldSpecialty = $profile->specialty;
            $oldStatus = $profile->status;

            if (isset($data['specialty'])) {
                $profile->specialty = $data['specialty'];
            }

            if (isset($data['status'])) {
                $profile->status = $data['status'];
            }

            $profile->save();

            if ($profile->user) {
                if (! empty($data['name'])) {
                    $profile->user->name = $data['name'];
                }
                if (! empty($data['email'])) {
                    $profile->user->email = $data['email'];
                }
                if (isset($data['roles']) && is_array($data['roles'])) {
                    $profile->user->syncRoles($data['roles']);
                }
                $profile->user->save();
            }

            Audit::log('staff_profile_updated', $profile, [
                'old_specialty' => $oldSpecialty,
                'new_specialty' => $profile->specialty,
                'old_status' => $oldStatus,
                'new_status' => $profile->status,
            ], $actor?->id);

            return $profile;
        });
    }

    public function updateStatus(StaffProfile $profile, string $newStatus): StaffProfile
    {
        return DB::transaction(function () use ($profile, $newStatus): StaffProfile {
            $oldStatus = $profile->status;
            $profile->forceFill(['status' => $newStatus])->save();

            Audit::log('staff_status_changed', $profile, [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            return $profile;
        });
    }

    public function deleteProfile(StaffProfile $profile): void
    {
        DB::transaction(function () use ($profile): void {
            $profile->forceFill(['status' => StaffProfile::STATUS_INACTIVO])->save();
            $profile->delete();

            Audit::log('staff_profile_deleted', $profile);
        });
    }

    public function createShift(User $user, array $data): Shift
    {
        return DB::transaction(function () use ($user, $data): Shift {
            $shift = Shift::create([
                'user_id' => $user->id,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'type' => $data['type'] ?? 'diurno',
                'status' => Shift::STATUS_PROGRAMADO,
            ]);

            Audit::log('shift_created', $shift);

            return $shift;
        });
    }

    public function startShift(Shift $shift): Shift
    {
        return DB::transaction(function () use ($shift): Shift {
            $shift->forceFill(['status' => Shift::STATUS_ACTIVO])->save();

            Audit::log('shift_started', $shift);

            return $shift;
        });
    }

    public function endShift(Shift $shift, int $activeEpisodesCount = 0): Shift
    {
        if ($activeEpisodesCount > 0) {
            throw ValidationException::withMessages([
                'shift' => "No se puede finalizar el turno. El profesional tiene {$activeEpisodesCount} paciente(s) asignado(s) pendientes de reasignación.",
            ]);
        }

        return DB::transaction(function () use ($shift): Shift {
            $shift->forceFill(['status' => Shift::STATUS_FINALIZADO])->save();

            Audit::log('shift_ended', $shift);

            return $shift;
        });
    }
}
