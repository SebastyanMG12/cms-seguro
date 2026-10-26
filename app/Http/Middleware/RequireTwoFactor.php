<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactor
{
    /** Roles cuyo uso exige segundo factor. */
    private const ROLES = ['administrador', 'auditor', 'coordinador', 'medico', 'enfermero', 'admisiones'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && ! $user->hasTwoFactorEnabled()
            && $user->hasAnyRole(self::ROLES)
            && ! $request->routeIs('two-factor.*', 'logout')
        ) {
            return redirect()->route('two-factor.show')
                ->with('success', 'Debe activar la verificación en dos pasos para continuar.');
        }

        return $next($request);
    }
}
