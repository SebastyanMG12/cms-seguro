<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Services\EmergencyMetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmergencyReportController extends Controller
{
    /**
     * Muestra el dashboard de reportes analíticos de urgencias y tiempos de espera por triage.
     */
    public function index(Request $request, EmergencyMetricsService $metricsService): View
    {
        Gate::authorize('reportes.ver');

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $selectedDate = ! empty($validated['date'])
            ? Carbon::createFromFormat('Y-m-d', $validated['date'])
            : now();

        $summary = $metricsService->getSummaryMetrics($selectedDate);
        $triageMetrics = $metricsService->getTriageWaitTimeMetrics($selectedDate);

        return view('reports.index', [
            'selectedDate' => $selectedDate,
            'summary' => $summary,
            'triageMetrics' => $triageMetrics,
            'triageTargets' => EmergencyMetricsService::TRIAGE_TARGETS,
        ]);
    }
}
