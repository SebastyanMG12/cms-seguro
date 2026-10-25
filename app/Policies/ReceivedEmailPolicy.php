<?php

namespace App\Policies;

use App\Models\ReceivedEmail;
use App\Models\User;

class ReceivedEmailPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('correos.ver');
    }

    public function view(User $user, ReceivedEmail $email): bool
    {
        return $user->hasPermissionTo('correos.ver');
    }
}