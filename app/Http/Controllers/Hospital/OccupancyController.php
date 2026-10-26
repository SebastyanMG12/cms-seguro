<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\PhysicalResourceService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OccupancyController extends Controller
{
    public function __construct(private PhysicalResourceService $resourceService) {}

    public function index(): View
    {
        Gate::authorize('ocupacion.ver');

        $report = $this->resourceService->getOccupancyReport();
        $sites = Site::with(['floors.areas.rooms.beds.activeAssignment'])->get();

        return view('occupancy.index', [
            'report' => $report,
            'sites' => $sites,
        ]);
    }
}
