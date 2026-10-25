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
        return $user->can('episodios.atender') && ($episode->isOpen() || request()->input('type') === 'aclaracion');
    }

    public function discharge(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.egreso') && $episode->status === 'ingresado';
    }

    public function assignStaff(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.asignar_personal') && $episode->isOpen();
    }

    public function assignBed(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.asignar_camilla') && $episode->status === 'ingresado';
    }

    public function updateCompanion(User $user, EmergencyEpisode $episode): bool
    {
        return $user->can('episodios.ver') && $episode->isOpen();
    }
}
