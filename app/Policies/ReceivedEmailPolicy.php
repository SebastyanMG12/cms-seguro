<?php

namespace App\Policies;

use App\Models\ReceivedEmail;
use App\Models\User;

class ReceivedEmailPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin', 'editor');
    }

    public function view(User $user, ReceivedEmail $email): bool
    {
        return $user->hasRole('admin', 'editor');
    }
}