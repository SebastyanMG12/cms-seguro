<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('usuarios.gestionar');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('usuarios.gestionar');
    }

    public function create(User $actor): bool
    {
        return $actor->can('usuarios.gestionar');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can('usuarios.gestionar');
    }

    public function updateRoles(User $actor, User $user): bool
    {
        return $actor->can('usuarios.gestionar') && $actor->id !== $user->id;
    }

    public function suspend(User $actor, User $user): bool
    {
        return $actor->can('usuarios.gestionar') && $actor->id !== $user->id;
    }

    public function reactivate(User $actor, User $user): bool
    {
        return $actor->can('usuarios.gestionar');
    }

    public function resetTwoFactor(User $actor, User $user): bool
    {
        return $actor->can('usuarios.gestionar');
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->can('usuarios.gestionar') && $actor->id !== $user->id;
    }
}
