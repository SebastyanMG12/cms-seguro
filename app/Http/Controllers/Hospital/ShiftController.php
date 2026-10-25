<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    public function __construct(private StaffService $staffService) {}

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('turnos.gestionar');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'type' => ['required', 'string', Rule::in(['diurno', 'nocturno', 'guardia'])],
        ]);

        $user = User::findOrFail($data['user_id']);
        $this->staffService->createShift($user, $data);

        return back()->with('success', "Turno programado para {$user->name}.");
    }

    public function start(Shift $shift): RedirectResponse
    {
        Gate::authorize('turnos.gestionar');

        $this->staffService->startShift($shift);

        return back()->with('success', 'Turno iniciado.');
    }

    public function end(Shift $shift): RedirectResponse
    {
        Gate::authorize('turnos.gestionar');

        $this->staffService->endShift($shift, 0);

        return back()->with('success', 'Turno finalizado con éxito.');
    }
}
