<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
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

        if ($user->hasRole('administrador') && $user->isActive() && ! in_array('administrador', $data['roles'], true)) {
            $this->ensureNotLastActiveAdmin($user, 'roles', 'No se puede quitar el rol al último administrador activo.');
        }

        return DB::transaction(function () use ($user, $data): User {
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

        if ($user->hasRole('administrador') && $user->isActive()) {
            $this->ensureNotLastActiveAdmin($user, 'user', 'No se puede suspender al último administrador activo.');
        }

        DB::transaction(function () use ($user, $reason): void {
            $user->forceFill(['status' => 'suspended'])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();

            Audit::log('user_suspended', $user, ['reason' => $reason]);
        });
    }

    public function reactivate(User $user, string $reason, User $actor): void
    {
        DB::transaction(function () use ($user, $reason): void {
            $user->forceFill(['status' => 'active'])->save();

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

        if ($user->hasRole('administrador') && $user->isActive()) {
            $this->ensureNotLastActiveAdmin($user, 'user', 'No se puede eliminar al último administrador activo.');
        }

        DB::transaction(function () use ($user): void {
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

    private function ensureNotLastActiveAdmin(User $user, string $errorKey, string $errorMessage): void
    {
        $activeAdminsCount = User::role('administrador')
            ->where('status', 'active')
            ->lockForUpdate()
            ->get()
            ->count();

        if ($activeAdminsCount <= 1) {
            throw ValidationException::withMessages([
                $errorKey => $errorMessage,
            ]);
        }
    }
}
