<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\EmergencyEpisode;
use App\Models\EpisodeAssignment;
use App\Models\Shift;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $isMedicalStaff = $user->hasAnyRole(['medico', 'enfermero']) || $user->staffProfile !== null;

        if ($isMedicalStaff) {
            $staffProfile = $user->staffProfile;

            // Turno activo en curso
            $activeShift = Shift::where('user_id', $user->id)
                ->where('status', Shift::STATUS_ACTIVO)
                ->first();

            // Próximo turno programado si no tiene turno activo
            $nextShift = null;
            if (! $activeShift) {
                $nextShift = Shift::where('user_id', $user->id)
                    ->where('status', Shift::STATUS_PROGRAMADO)
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at', 'asc')
                    ->first();
            }

            // Pacientes activos asignados a este profesional (deduplicados por episodio)
            $activeAssignments = EpisodeAssignment::with([
                'episode.patient',
                'episode.activeBedAssignment.bed.room',
                'episode.responsibleUser.staffProfile',
            ])
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->whereHas('episode', fn ($q) => $q->whereIn('status', ['en_valoracion', 'ingresado']))
                ->orderBy('started_at', 'desc')
                ->get()
                ->unique('episode_id');

            // Métricas del profesional
            $myPatientsCount = $activeAssignments->count();
            $asResponsibleCount = $activeAssignments->where('role', 'responsable')->count();
            $asSupportCount = $activeAssignments->where('role', 'apoyo')->count();
            $criticalPatientsCount = $activeAssignments->filter(function ($a) {
                return in_array($a->episode?->triage_level, ['I', 'II'], true);
            })->count();

            // Resumen de urgencias del hospital
            $hospitalWaitingCount = EmergencyEpisode::where('status', 'en_espera')->count();
            $hospitalActiveCount = EmergencyEpisode::whereIn('status', ['en_valoracion', 'ingresado'])->count();
            $availableBedsCount = Bed::where('status', Bed::STATUS_DISPONIBLE)->count();
            $totalBedsCount = Bed::count();

            return view('dashboard', [
                'isMedicalStaff' => true,
                'staffProfile' => $staffProfile,
                'activeShift' => $activeShift,
                'nextShift' => $nextShift,
                'activeAssignments' => $activeAssignments,
                'myPatientsCount' => $myPatientsCount,
                'asResponsibleCount' => $asResponsibleCount,
                'asSupportCount' => $asSupportCount,
                'criticalPatientsCount' => $criticalPatientsCount,
                'hospitalWaitingCount' => $hospitalWaitingCount,
                'hospitalActiveCount' => $hospitalActiveCount,
                'availableBedsCount' => $availableBedsCount,
                'totalBedsCount' => $totalBedsCount,
            ]);
        }

        // Panel de control operativo general para administradores y coordinadores
        $hospitalWaitingCount = EmergencyEpisode::where('status', 'en_espera')->count();
        $hospitalActiveCount = EmergencyEpisode::whereIn('status', ['en_valoracion', 'ingresado'])->count();
        $hospitalPreregCount = EmergencyEpisode::where('status', 'preregistrado')->count();
        $hospitalDischargedCount = EmergencyEpisode::where('status', 'egresado')->count();
        $occupiedBedsCount = Bed::where('status', Bed::STATUS_OCUPADA)->count();
        $availableBedsCount = Bed::where('status', Bed::STATUS_DISPONIBLE)->count();
        $maintenanceBedsCount = Bed::whereIn('status', [Bed::STATUS_MANTENIMIENTO, Bed::STATUS_FUERA_DE_SERVICIO])->count();
        $activeShiftsCount = Shift::where('status', Shift::STATUS_ACTIVO)->count();
        $totalStaffCount = StaffProfile::count();
        $totalUsersCount = User::count();

        return view('dashboard', [
            'isMedicalStaff' => false,
            'hospitalWaitingCount' => $hospitalWaitingCount,
            'hospitalActiveCount' => $hospitalActiveCount,
            'hospitalPreregCount' => $hospitalPreregCount,
            'hospitalDischargedCount' => $hospitalDischargedCount,
            'occupiedBedsCount' => $occupiedBedsCount,
            'availableBedsCount' => $availableBedsCount,
            'maintenanceBedsCount' => $maintenanceBedsCount,
            'activeShiftsCount' => $activeShiftsCount,
            'totalStaffCount' => $totalStaffCount,
            'totalUsersCount' => $totalUsersCount,
        ]);
    }
}
