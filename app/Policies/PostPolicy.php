<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('publicaciones.ver');
    }

    public function view(User $user, Post $post): bool
    {
        if ($post->status === 'published') {
            return true;
        }

        return $user->can('publicaciones.ver') && ($user->id === $post->user_id || $user->hasAnyRole(['administrador', 'editor']));
    }

    public function create(User $user): bool
    {
        return $user->can('publicaciones.crear');
    }

    public function update(User $user, Post $post): bool
    {
        if (! $user->can('publicaciones.editar')) {
            return false;
        }

        return $user->id === $post->user_id || $user->hasAnyRole(['administrador', 'editor']);
    }

    public function publish(User $user, ?Post $post = null): bool
    {
        return $user->can('publicaciones.publicar');
    }

    public function delete(User $user, Post $post): bool
    {
        if (! $user->can('publicaciones.eliminar')) {
            return false;
        }

        return $user->id === $post->user_id || $user->hasRole('administrador');
    }
}
