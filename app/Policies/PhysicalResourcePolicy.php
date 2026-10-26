<?php

namespace App\Policies;

use App\Models\User;

class PhysicalResourcePolicy
{
    public function manageSites(User $user): bool
    {
        return $user->can('sedes.gestionar');
    }

    public function manageRooms(User $user): bool
    {
        return $user->can('habitaciones.gestionar');
    }

    public function manageBeds(User $user): bool
    {
        return $user->can('camillas.gestionar');
    }

    public function viewOccupancy(User $user): bool
    {
        return $user->can('ocupacion.ver');
    }
}
