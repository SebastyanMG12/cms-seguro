<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private StaffService $staffService) {}

    public function index(): View
    {
        Gate::authorize('personal.gestionar');

        $staff = StaffProfile::with(['user.roles', 'user.shifts' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('id', 'desc')
            ->paginate(20);

        $eligibleUsers = User::role(['medico', 'enfermero', 'coordinador'])
            ->whereDoesntHave('staffProfile')
            ->orderBy('name')
            ->get();

        return view('staff.index', [
            'staff' => $staff,
            'eligibleUsers' => $eligibleUsers,
        ]);
    }

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
