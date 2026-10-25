<?php

namespace App\Policies;

use App\Models\User;

class StaffPolicy
{
    public function manageStaff(User $user): bool
    {
        return $user->can('personal.gestionar');
    }

    public function manageShifts(User $user): bool
    {
        return $user->can('turnos.gestionar');
    }
}
