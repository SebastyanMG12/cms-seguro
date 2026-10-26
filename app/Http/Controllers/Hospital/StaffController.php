<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\EpisodeAssignment;
use App\Models\Shift;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\ClinicalService;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(
        private StaffService $staffService,
        private ClinicalService $clinicalService
    ) {}

    public function index(): View
    {
        Gate::authorize('personal.gestionar');

        $staff = StaffProfile::with([
            'user.roles',
            'user.shifts' => fn ($q) => $q->latest()->limit(5),
            'user.activeEpisodeAssignments' => fn ($q) => $q->with([
                'episode.patient',
                'episode.activeBedAssignment.bed.room',
            ]),
        ])
            ->orderBy('id', 'desc')
            ->paginate(20);

        // Adjuntar cálculos dinámicos de turno activo y pacientes asignados por profesional
        $staff->each(function (StaffProfile $profile) {
            $profile->active_assignments = $profile->user->activeEpisodeAssignments->unique('episode_id');
            $profile->active_patients_count = $profile->active_assignments->count();
            $profile->active_shift = $profile->user->shifts->firstWhere('status', Shift::STATUS_ACTIVO);
        });

        $shifts = Shift::with(['user.staffProfile', 'user.roles'])
            ->latest('starts_at')
            ->paginate(15, ['*'], 'shifts_page');

        $availableUsers = User::role(['medico', 'enfermero', 'coordinador'])
            ->whereDoesntHave('staffProfile')
            ->orderBy('name')
            ->get();

        // Métricas operacionales para tarjetas de resumen
        $totalStaff = StaffProfile::count();
        $activeShiftsCount = Shift::where('status', Shift::STATUS_ACTIVO)->count();
        $onRestCount = StaffProfile::where('status', StaffProfile::STATUS_EN_DESCANSO)->count();
        $totalActivePatients = EpisodeAssignment::whereNull('ended_at')
            ->whereHas('episode', fn ($q) => $q->whereNotIn('status', ['egresado', 'expirado']))
            ->count();

        return view('staff.index', [
            'staff' => $staff,
            'shifts' => $shifts,
            'availableUsers' => $availableUsers,
            'totalStaff' => $totalStaff,
            'activeShiftsCount' => $activeShiftsCount,
            'onRestCount' => $onRestCount,
            'totalActivePatients' => $totalActivePatients,
        ]);
    }

    /**
     * Vincula un usuario existente con un perfil de personal asistencial.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('personal.gestionar');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'specialty' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $profile = $this->staffService->createProfile($user, $data['specialty']);

        return back()->with('success', "Perfil de personal creado para {$user->name} con código {$profile->code}.");
    }

    /**
     * Registra un nuevo profesional clínico desde cero (Usuario + Rol + Perfil Asistencial).
     */
    public function storeWithUser(Request $request): RedirectResponse
    {
        Gate::authorize('personal.gestionar');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(['medico', 'enfermero', 'coordinador'])],
            'specialty' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $profile = $this->staffService->createStaffWithUser($data, $request->user());

        return back()->with('success', "Profesional {$profile->user->name} registrado exitosamente con código {$profile->code}.");
    }

    /**
     * Formulario de edición de un profesional asistencial.
     */
    public function edit(StaffProfile $profile): View
    {
        Gate::authorize('personal.gestionar');

        $profile->load(['user.roles']);

        return view('staff.edit', [
            'profile' => $profile,
            'availableRoles' => ['medico', 'enfermero', 'coordinador'],
            'activePatientsCount' => $this->clinicalService->countActivePatients($profile->user),
        ]);
    }

    /**
     * Actualiza los datos del profesional asistencial y su perfil.
     */
    public function update(Request $request, StaffProfile $profile): RedirectResponse
    {
        Gate::authorize('personal.gestionar');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($profile->user_id)],
            'specialty' => ['required', 'string', 'min:2', 'max:100'],
            'status' => ['required', 'string', Rule::in([
                StaffProfile::STATUS_ACTIVO,
                StaffProfile::STATUS_EN_DESCANSO,
                StaffProfile::STATUS_INACTIVO,
            ])],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(['medico', 'enfermero', 'coordinador'])],
        ]);

        $this->staffService->updateProfile($profile, $data, $request->user());

        return redirect()->route('staff.index')
            ->with('success', "Datos del profesional {$profile->user->name} actualizados exitosamente.");
    }

    public function updateStatus(Request $request, StaffProfile $profile): RedirectResponse
    {
        Gate::authorize('personal.gestionar');

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([
                StaffProfile::STATUS_ACTIVO,
                StaffProfile::STATUS_INACTIVO,
                StaffProfile::STATUS_EN_DESCANSO,
            ])],
        ]);

        $this->staffService->updateStatus($profile, $data['status']);

        return back()->with('success', "Estado del profesional {$profile->user->name} actualizado a {$data['status']}.");
    }

    public function destroy(StaffProfile $profile): RedirectResponse
    {
        Gate::authorize('personal.gestionar');

        $this->staffService->deleteProfile($profile);

        return back()->with('success', 'Perfil de personal desactivado.');
    }
}
