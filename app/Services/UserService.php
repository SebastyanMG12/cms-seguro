<?php

namespace App\Services;

use App\Models\Shift;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * Clave fija de 64 bits para el candado de asesoría transaccional en PostgreSQL.
     * Serializa operaciones sobre administradores activos para evitar condiciones de carrera.
     */
    private const ADMIN_LOCK_KEY = 742910384;

    /**
     * @param  array{name: string, email: string, roles: array<int, string>}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        $currentRoles = $user->roles->pluck('name')->sort()->values()->all();
        $newRoles = collect($data['roles'])->sort()->values()->all();
        $rolesChanged = $currentRoles !== $newRoles;

        if ($actor->id === $user->id && $rolesChanged) {
            throw ValidationException::withMessages([
                'roles' => 'No puede modificar sus propios roles.',
            ]);
        }

        return DB::transaction(function () use ($user, $data): User {
            if ($user->hasRole('administrador') && $user->isActive() && ! in_array('administrador', $data['roles'], true)) {
                $this->ensureNotLastActiveAdmin('roles', 'No se puede quitar el rol al último administrador activo.');
            }

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $user->syncRoles($data['roles']);

            Audit::log('user_updated', $user, ['roles' => $data['roles']]);

            return $user;
        });
    }

    public function suspend(User $user, string $reason, User $actor): void
    {
        if ($actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'No puede suspender su propia cuenta.',
            ]);
        }

        if ($user->staffProfile()->exists()) {
            $activePatients = app(ClinicalService::class)->countActivePatients($user);
            if ($activePatients > 0) {
                throw ValidationException::withMessages([
                    'user' => "No se puede suspender al usuario. El profesional tiene {$activePatients} paciente(s) activos asignados en urgencias. Debe reasignar sus pacientes antes de suspender su cuenta.",
                ]);
            }
        }

        DB::transaction(function () use ($user, $reason): void {
            if ($user->hasRole('administrador') && $user->isActive()) {
                $this->ensureNotLastActiveAdmin('user', 'No se puede suspender al último administrador activo.');
            }

            $user->forceFill(['status' => 'suspended'])->save();

            // Sincronizar estado asistencial
            if ($user->staffProfile) {
                $user->staffProfile->forceFill(['status' => StaffProfile::STATUS_INACTIVO])->save();
            }

            // Finalizar turnos activos por suspensión
            $user->shifts()->where('status', Shift::STATUS_ACTIVO)->update(['status' => Shift::STATUS_FINALIZADO]);

            DB::table('sessions')->where('user_id', $user->id)->delete();

            Audit::log('user_suspended', $user, ['reason' => $reason]);
        });
    }

    public function reactivate(User $user, string $reason, User $actor): void
    {
        DB::transaction(function () use ($user, $reason): void {
            $user->forceFill(['status' => 'active'])->save();

            if ($user->staffProfile) {
                $user->staffProfile->forceFill(['status' => StaffProfile::STATUS_ACTIVO])->save();
            }

            Audit::log('user_reactivated', $user, ['reason' => $reason]);
        });
    }

    public function resetTwoFactor(User $user, string $reason, User $actor): void
    {
        DB::transaction(function () use ($user, $reason): void {
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_timestep' => null,
            ])->save();

            Audit::log('two_factor_reset', $user, ['reason' => $reason]);
        });
    }

    public function delete(User $user, User $actor): void
    {
        if ($actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => 'No puede eliminar su propia cuenta.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            if ($user->hasRole('administrador') && $user->isActive()) {
                $this->ensureNotLastActiveAdmin('user', 'No se puede eliminar al último administrador activo.');
            }

            $user->delete();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            Audit::log('user_deleted', $user);
        });
    }

    public function changePassword(User $user, string $newPassword, ?string $currentSessionId = null): void
    {
        DB::transaction(function () use ($user, $newPassword, $currentSessionId): void {
            $user->forceFill(['password' => Hash::make($newPassword)])->save();

            $query = DB::table('sessions')->where('user_id', $user->id);
            if ($currentSessionId !== null && $currentSessionId !== '') {
                $query->where('id', '!=', $currentSessionId);
            }
            $query->delete();

            Audit::log('password_changed', $user, [], $user->id);
        });
    }

    private function ensureNotLastActiveAdmin(string $errorKey, string $errorMessage): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(?)', [self::ADMIN_LOCK_KEY]);
        }

        $activeAdminsCount = User::role('administrador')
            ->where('status', 'active')
            ->count();

        if ($activeAdminsCount <= 1) {
            throw ValidationException::withMessages([
                $errorKey => $errorMessage,
            ]);
        }
    }
}
