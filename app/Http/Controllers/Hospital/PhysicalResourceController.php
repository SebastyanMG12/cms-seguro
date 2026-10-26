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

        return back()->with('success', "Estado de habitación {$room->code} y sus camillas actualizado a {$data['status']}.");
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        Gate::authorize('habitaciones.gestionar');

        $data = $request->validate([
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $this->resourceService->updateRoom($room, $data);

        return back()->with('success', "Capacidad de la habitación {$room->code} actualizada a {$data['capacity']} camillas.");
    }

    public function storeBed(Room $room): RedirectResponse
    {
        Gate::authorize('camillas.gestionar');

        $bed = $this->resourceService->createBed($room);

        return back()->with('success', "Camilla {$bed->code} agregada y habilitada exitosamente.");
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

        return back()->with('success', "Estado de camilla {$bed->code} actualizado a {$data['status']}.");
    }

    public function releaseBed(Request $request, Bed $bed): RedirectResponse
    {
        Gate::authorize('camillas.gestionar');

        $this->resourceService->releaseBed($bed, Bed::STATUS_EN_LIMPIEZA);

        return back()->with('success', "Camilla {$bed->code} liberada y puesta en limpieza.");
    }

    public function destroyBed(Bed $bed): RedirectResponse
    {
        Gate::authorize('camillas.gestionar');

        $bedCode = $bed->code;
        $this->resourceService->deleteBed($bed);

        return back()->with('success', "Camilla {$bedCode} eliminada exitosamente. Se ha liberado un espacio en la sala.");
    }
}
