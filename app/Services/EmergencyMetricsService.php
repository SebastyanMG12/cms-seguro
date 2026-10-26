<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\EmergencyEpisode;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EmergencyMetricsService
{
    /**
     * Definición de metas de tiempo de espera según Resolución 5596 de 2015 del Ministerio de Salud.
     */
    public const TRIAGE_TARGETS = [
        'I' => [
            'name' => 'Triage I - Resucitación',
            'target_minutes' => 0,
            'description' => 'Atención médica inmediata. Riesgo vital inminente.',
            'color' => 'danger',
        ],
        'II' => [
            'name' => 'Triage II - Emergencia',
            'target_minutes' => 30,
            'description' => 'Máximo 30 minutos. Riesgo vital potencial o dolor extremo.',
            'color' => 'warning',
        ],
        'III' => [
            'name' => 'Triage III - Urgencia',
            'target_minutes' => 120,
            'description' => 'Máximo 120 minutos (2 horas). Paciente estable con posible deterioro.',
            'color' => 'warning',
        ],
        'IV' => [
            'name' => 'Triage IV - Prioritario',
            'target_minutes' => 180,
            'description' => 'Máximo 180 minutos (3 horas). Urgencia menor o sintomática.',
            'color' => 'success',
        ],
        'V' => [
            'name' => 'Triage V - No Urgente',
            'target_minutes' => 240,
            'description' => 'Máximo 240 minutos (4 horas). Problemas no agudos.',
            'color' => 'info',
        ],
    ];

    /**
     * Obtiene las métricas consolidadas del servicio de urgencias para una fecha dada (por defecto hoy).
     */
    public function getSummaryMetrics(?Carbon $date = null): array
    {
        $targetDate = $date ?? now();
        $startOfDay = $targetDate->copy()->startOfDay();
        $endOfDay = $targetDate->copy()->endOfDay();

        // 1. Conteo de episodios por estado para pacientes que ingresaron hoy
        $episodesToday = EmergencyEpisode::whereBetween('arrived_at', [$startOfDay, $endOfDay])->get();

        $episodesByStatus = [
            'preregistrado' => EmergencyEpisode::where('status', 'preregistrado')->count(),
            'en_espera' => EmergencyEpisode::where('status', 'en_espera')->count(),
            'en_valoracion' => EmergencyEpisode::where('status', 'en_valoracion')->count(),
            'ingresado' => EmergencyEpisode::where('status', 'ingresado')->count(),
            'egresado_hoy' => EmergencyEpisode::where('status', 'egresado')->whereBetween('discharged_at', [$startOfDay, $endOfDay])->count(),
            'activos_total' => EmergencyEpisode::whereIn('status', ['en_espera', 'en_valoracion', 'ingresado'])->count(),
            'nn_activos' => EmergencyEpisode::where('is_nn', true)->whereIn('status', ['en_espera', 'en_valoracion', 'ingresado'])->count(),
        ];

        // 2. Conteo de camillas
        $bedCounts = Bed::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalBeds = array_sum($bedCounts);
        $occupiedBeds = $bedCounts[Bed::STATUS_OCUPADA] ?? 0;
        $availableBeds = $bedCounts[Bed::STATUS_DISPONIBLE] ?? 0;
        $maintenanceBeds = ($bedCounts[Bed::STATUS_MANTENIMIENTO] ?? 0) + ($bedCounts[Bed::STATUS_FUERA_DE_SERVICIO] ?? 0);
        $cleaningBeds = $bedCounts[Bed::STATUS_EN_LIMPIEZA] ?? 0;
        $occupancyRate = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;

        // 3. Personal en turno activo
        $activeShifts = Shift::with(['user.staffProfile', 'user.roles'])
            ->where('status', Shift::STATUS_ACTIVO)
            ->get();

        $staffInShift = [
            'total' => $activeShifts->count(),
            'medicos' => $activeShifts->filter(fn ($s) => $s->user && $s->user->hasRole('medico'))->count(),
            'enfermeros' => $activeShifts->filter(fn ($s) => $s->user && $s->user->hasRole('enfermero'))->count(),
        ];

        return [
            'date' => $targetDate,
            'episodes' => $episodesByStatus,
            'total_arrived_today' => $episodesToday->count(),
            'beds' => [
                'total' => $totalBeds,
                'occupied' => $occupiedBeds,
                'available' => $availableBeds,
                'maintenance' => $maintenanceBeds,
                'cleaning' => $cleaningBeds,
                'occupancy_rate' => $occupancyRate,
            ],
            'staff' => $staffInShift,
        ];
    }

    /**
     * Calcula los tiempos reales de espera por nivel de triage (Res. 5596 de 2015).
     */
    public function getTriageWaitTimeMetrics(?Carbon $date = null): array
    {
        $targetDate = $date ?? now();
        $startOfDay = $targetDate->copy()->startOfDay();
        $endOfDay = $targetDate->copy()->endOfDay();

        // Obtener episodios triados que llegaron en la fecha seleccionada
        $episodes = EmergencyEpisode::with(['clinicalEntries' => function ($q) {
            $q->orderBy('performed_at', 'asc');
        }])
            ->whereBetween('arrived_at', [$startOfDay, $endOfDay])
            ->whereNotNull('triage_level')
            ->whereNotNull('triage_at')
            ->get();

        $metricsByLevel = [];

        foreach (['I', 'II', 'III', 'IV', 'V'] as $level) {
            $levelDefinition = self::TRIAGE_TARGETS[$level];
            $targetMinutes = $levelDefinition['target_minutes'];

            $levelEpisodes = $episodes->where('triage_level', $level);
            $totalCount = $levelEpisodes->count();

            $waitTimesMinutes = [];
            $withinTargetCount = 0;
            $currentlyWaitingCount = 0;

            foreach ($levelEpisodes as $episode) {
                if ($episode->status === 'en_espera') {
                    $currentlyWaitingCount++;
                }

                // Primera atención médica (primera entrada clínica del médico)
                $firstMedicalEntry = $episode->clinicalEntries->first();

                if ($firstMedicalEntry && $firstMedicalEntry->performed_at && $episode->triage_at) {
                    $waitMinutes = max(0, (int) $episode->triage_at->diffInMinutes($firstMedicalEntry->performed_at));
                    $waitTimesMinutes[] = $waitMinutes;

                    if ($waitMinutes <= $targetMinutes) {
                        $withinTargetCount++;
                    }
                } elseif ($episode->status === 'en_espera' && $episode->triage_at) {
                    // Paciente aún en espera: tiempo transcurrido hasta el momento actual
                    $currentWait = max(0, (int) $episode->triage_at->diffInMinutes(now()));
                    $waitTimesMinutes[] = $currentWait;

                    if ($currentWait <= $targetMinutes) {
                        $withinTargetCount++;
                    }
                }
            }

            $evaluatedCount = count($waitTimesMinutes);
            $avgMinutes = $evaluatedCount > 0 ? (int) round(array_sum($waitTimesMinutes) / $evaluatedCount) : 0;
            $maxMinutes = $evaluatedCount > 0 ? max($waitTimesMinutes) : 0;
            $complianceRate = $evaluatedCount > 0 ? round(($withinTargetCount / $evaluatedCount) * 100, 1) : 100.0;

            $metricsByLevel[$level] = [
                'level' => $level,
                'name' => $levelDefinition['name'],
                'target_minutes' => $targetMinutes,
                'description' => $levelDefinition['description'],
                'color' => $levelDefinition['color'],
                'total_patients' => $totalCount,
                'currently_waiting' => $currentlyWaitingCount,
                'avg_wait_minutes' => $avgMinutes,
                'max_wait_minutes' => $maxMinutes,
                'compliance_rate' => $complianceRate,
            ];
        }

        return $metricsByLevel;
    }
}
