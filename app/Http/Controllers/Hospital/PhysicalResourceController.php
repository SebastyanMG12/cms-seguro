<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Room;
use App\Services\PhysicalResourceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PhysicalResourceController extends Controller
{
    public function __construct(private PhysicalResourceService $resourceService) {}

    public function updateRoomStatus(Request $request, Room $room): RedirectResponse
    {
        Gate::authorize('habitaciones.gestionar');

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['disponible', 'fuera_de_servicio', 'mantenimiento'])],
        ]);

        $this->resourceService->updateRoomStatus($room, $data['status']);

        return back()->with('success', "Estado de habitación {$room->code} actualizado.");
    }

    public function storeBed(Room $room): RedirectResponse
    {
        Gate::authorize('camillas.gestionar');

        $bed = $this->resourceService->createBed($room);

        return back()->with('success', "Camilla {$bed->code} habilitada.");
    }

    public function updateBedStatus(Request $request, Bed $bed): RedirectResponse
    {
        Gate::authorize('camillas.gestionar');

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([
                Bed::STATUS_DISPONIBLE,
                Bed::STATUS_OCUPADA,
                Bed::STATUS_EN_LIMPIEZA,
                Bed::STATUS_MANTENIMIENTO,
                Bed::STATUS_FUERA_DE_SERVICIO,
                Bed::STATUS_RESERVADA,
            ])],
        ]);

        $this->resourceService->updateBedStatus($bed, $data['status']);

        return back()->with('success', "Estado de camilla {$bed->code} actualizado.");
    }

    public function releaseBed(Request $request, Bed $bed): RedirectResponse
    {
        Gate::authorize('camillas.gestionar');

        $this->resourceService->releaseBed($bed, Bed::STATUS_EN_LIMPIEZA);

        return back()->with('success', "Camilla {$bed->code} liberada y puesta en limpieza.");
    }
}
