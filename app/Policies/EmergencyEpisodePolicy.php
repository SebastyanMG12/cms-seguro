<?php

namespace App\Policies;

use App\Models\EmergencyEpisode;
use App\Models\User;

class EmergencyEpisodePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('episodios.ver');
    }

    public function view(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.ver');
    }

    public function registerArrival(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.llegada') && $episode->status === 'preregistrado';
    }

    public function assignTriage(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.triage') && in_array($episode->status, ['en_espera', 'en_valoracion'], true);
    }

    public function confirmAdmission(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.ingreso') && $episode->status === 'en_valoracion';
    }

    public function addClinicalEntry(User $user, EmergencyEpisode $episode): bool
    {
        if (! $user->can('episodios.atender')) {
            return false;
        }

        // Si es médico o enfermero, debe estar asignado activamente o ser coordinador/administrador
        if (! $episode->isAssignedUser($user) && ! $user->hasAnyRole(['coordinador', 'administrador'])) {
            return false;
        }

        if (in_array($episode->status, ['egresado', 'expirado'], true)) {
            return request()->input('type') === 'aclaracion';
        }

        return in_array($episode->status, ['en_valoracion', 'ingresado'], true);
    }

    public function discharge(User $user, EmergencyEpisode $episode): bool
    {
        if (! $user->can('episodios.egreso') || $episode->status !== 'ingresado') {
            return false;
        }

        // Solo el médico responsable asignado o la coordinación médica pueden dar el egreso
        return $episode->responsible_user_id === $user->id || $user->hasAnyRole(['coordinador', 'administrador']);
    }

    public function assignStaff(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.asignar_personal') && $episode->isOpen();
    }

    public function assignBed(User $user, EmergencyEpisode $episode): bool
    {
        if (! $user->can('episodios.asignar_camilla') || $episode->status !== 'ingresado') {
            return false;
        }

        return $episode->isAssignedUser($user) || $user->hasAnyRole(['coordinador', 'administrador']);
    }

    public function updateCompanion(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.ver') && $episode->isOpen();
    }

    public function createNn(User $user): bool
    {
        return $user->can('episodios.crear_nn');
    }

    public function reconcile(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.conciliar')
            && $episode->isNn()
            && ! $episode->isReconciled();
    }
}
