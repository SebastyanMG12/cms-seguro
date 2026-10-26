@extends('layouts.app')

@section('title', 'Reportes de Urgencias y Tiempos de Triage - Hospital de Ubaté')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0 fw-bold">
            <i class="bi bi-graph-up-arrow text-primary me-2" aria-hidden="true"></i>Reportes Analíticos y Tiempos de Urgencias
        </h1>
        <small class="text-muted">Indicadores en tiempo real y cumplimiento de tiempos de espera según Resolución 5596 de 2015</small>
    </div>
    <div>
        <form method="GET" action="{{ route('reports.index') }}" class="d-flex align-items-center gap-2">
            <label for="date" class="form-label small fw-semibold text-muted mb-0">Fecha:</label>
            <input type="date" name="date" id="date" value="{{ $selectedDate->format('Y-m-d') }}" class="form-control form-control-sm" style="width: auto;" onchange="this.form.submit();">
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Consultar
            </button>
        </form>
    </div>
</div>

{{-- Tarjetas de Métricas Generales del Día --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-start border-primary border-4 bg-white h-100">
            <div class="card-body p-3">
                <span class="text-muted small d-block">Ingresos en Fecha</span>
                <div class="h3 fw-bold mb-0 text-dark tabular-nums">{{ $summary['total_arrived_today'] }}</div>
                <small class="text-muted">Pacientes arribados hoy</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-start border-warning border-4 bg-white h-100">
            <div class="card-body p-3">
                <span class="text-muted small d-block">Pacientes Activos</span>
                <div class="h3 fw-bold mb-0 text-warning-emphasis tabular-nums">{{ $summary['episodes']['activos_total'] }}</div>
                <small class="text-muted">
                    {{ $summary['episodes']['en_espera'] }} en espera | {{ $summary['episodes']['en_valoracion'] }} valoración | {{ $summary['episodes']['ingresado'] }} obs.
                </small>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-start border-info border-4 bg-white h-100">
            <div class="card-body p-3">
                <span class="text-muted small d-block">Ocupación de Camillas</span>
                <div class="h3 fw-bold mb-0 text-info-emphasis tabular-nums">{{ $summary['beds']['occupancy_rate'] }}%</div>
                <small class="text-muted">
                    {{ $summary['beds']['occupied'] }} ocupadas de {{ $summary['beds']['total'] }} habilitadas
                </small>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 border-start border-success border-4 bg-white h-100">
            <div class="card-body p-3">
                <span class="text-muted small d-block">Egresos Concluidos</span>
                <div class="h3 fw-bold mb-0 text-success tabular-nums">{{ $summary['episodes']['egresado_hoy'] }}</div>
                <small class="text-muted">Atenciones cerradas hoy</small>
            </div>
        </div>
    </div>
</div>

{{-- Tablero de Tiempos de Espera por Nivel de Triage (Resolución 5596 de 2015) --}}
<div class="card shadow-sm border-0 mb-4 bg-white">
    <div class="card-header bg-white py-3 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="h5 mb-0 fw-bold text-dark">
                <i class="bi bi-stopwatch text-primary me-2" aria-hidden="true"></i>Tablero de Tiempos de Espera por Nivel de Triage
            </h2>
            <small class="text-muted">Resolución 5596 de 2015 - Ministerio de Salud y Protección Social de Colombia</small>
        </div>
        <span class="badge bg-light text-dark border">
            Fecha de cálculo: <strong class="tabular-nums">{{ $selectedDate->format('d/m/Y') }}</strong>
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-compact">
                <thead class="table-light">
                    <tr>
                        <th style="width: 20%;">Nivel de Triage</th>
                        <th style="width: 14%;">Meta Legal (Res. 5596)</th>
                        <th style="width: 10%;" class="text-center">Total Pacientes</th>
                        <th style="width: 10%;" class="text-center">En Espera Actual</th>
                        <th style="width: 14%;" class="text-center">Espera Promedio</th>
                        <th style="width: 12%;" class="text-center">Espera Máxima</th>
                        <th style="width: 20%;">Cumplimiento Meta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($triageMetrics as $metric)
                        <tr>
                            <td>
                                @switch($metric['level'])
                                    @case('I')
                                        <span class="badge bg-danger text-white fs-6 font-monospace px-2 py-1">Triage I</span>
                                        <div class="fw-semibold text-danger small mt-1">Resucitación (Inmediato)</div>
                                        @break
                                    @case('II')
                                        <span class="badge bg-warning text-dark fs-6 font-monospace px-2 py-1">Triage II</span>
                                        <div class="fw-semibold text-warning-emphasis small mt-1">Emergencia (<= 30 min)</div>
                                        @break
                                    @case('III')
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning fs-6 font-monospace px-2 py-1">Triage III</span>
                                        <div class="fw-semibold text-dark small mt-1">Urgencia (<= 120 min)</div>
                                        @break
                                    @case('IV')
                                        <span class="badge bg-success text-white fs-6 font-monospace px-2 py-1">Triage IV</span>
                                        <div class="fw-semibold text-success small mt-1">Prioritario (<= 180 min)</div>
                                        @break
                                    @case('V')
                                        <span class="badge bg-info text-dark fs-6 font-monospace px-2 py-1">Triage V</span>
                                        <div class="fw-semibold text-info-emphasis small mt-1">No Urgente (<= 240 min)</div>
                                        @break
                                @endswitch
                            </td>
                            <td class="font-monospace tabular-nums">
                                @if ($metric['target_minutes'] === 0)
                                    <strong class="text-danger">0 min (Inmediato)</strong>
                                @else
                                    <strong>&le; {{ $metric['target_minutes'] }} min</strong>
                                    <span class="text-muted small d-block">({{ $metric['target_minutes'] / 60 }} h)</span>
                                @endif
                            </td>
                            <td class="text-center font-monospace tabular-nums fw-bold fs-6">
                                {{ $metric['total_patients'] }}
                            </td>
                            <td class="text-center font-monospace tabular-nums">
                                @if ($metric['currently_waiting'] > 0)
                                    <span class="badge bg-warning text-dark px-2 py-1">{{ $metric['currently_waiting'] }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-center font-monospace tabular-nums">
                                @if ($metric['total_patients'] > 0)
                                    <strong class="@if ($metric['avg_wait_minutes'] > $metric['target_minutes']) text-danger @else text-success @endif">
                                        {{ $metric['avg_wait_minutes'] }} min
                                    </strong>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center font-monospace tabular-nums">
                                @if ($metric['total_patients'] > 0)
                                    <span class="@if ($metric['max_wait_minutes'] > $metric['target_minutes']) text-danger fw-bold @else text-muted @endif">
                                        {{ $metric['max_wait_minutes'] }} min
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if ($metric['total_patients'] > 0)
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 14px;" role="progressbar" aria-valuenow="{{ $metric['compliance_rate'] }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-bar @if ($metric['compliance_rate'] >= 90) bg-success @elseif ($metric['compliance_rate'] >= 75) bg-warning @else bg-danger @endif" style="width: {{ $metric['compliance_rate'] }}%;"></div>
                                        </div>
                                        <span class="font-monospace tabular-nums small fw-bold text-nowrap">{{ $metric['compliance_rate'] }}%</span>
                                    </div>
                                @else
                                    <span class="text-muted small">Sin atenciones registradas</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-light py-2 px-3 small text-muted">
        <i class="bi bi-info-circle me-1 text-primary"></i>
        Los tiempos de espera se calculan desde la asignación del triage hasta la primera valoración médica registrada en la historia clínica.
    </div>
</div>

{{-- Desglose de Camillas y Personal Asistencial --}}
<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 bg-white h-100">
            <div class="card-header bg-white py-2 px-3 fw-bold text-dark">
                <i class="bi bi-hospital me-1 text-primary"></i>Censo Institucional de Camillas
            </div>
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-muted">Total de Camillas Habilitadas:</span>
                    <strong class="font-monospace tabular-nums">{{ $summary['beds']['total'] }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-danger"><i class="bi bi-circle-fill me-1 small"></i>Camillas Ocupadas:</span>
                    <strong class="font-monospace tabular-nums text-danger">{{ $summary['beds']['occupied'] }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-success"><i class="bi bi-circle-fill me-1 small"></i>Camillas Disponibles:</span>
                    <strong class="font-monospace tabular-nums text-success">{{ $summary['beds']['available'] }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-secondary"><i class="bi bi-circle-fill me-1 small"></i>En Mantenimiento / Fuera de Servicio:</span>
                    <strong class="font-monospace tabular-nums text-secondary">{{ $summary['beds']['maintenance'] }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-info"><i class="bi bi-circle-fill me-1 small"></i>En Limpieza y Desinfección:</span>
                    <strong class="font-monospace tabular-nums text-info">{{ $summary['beds']['cleaning'] }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-0 bg-white h-100">
            <div class="card-header bg-white py-2 px-3 fw-bold text-dark">
                <i class="bi bi-person-badge me-1 text-primary"></i>Personal Asistencial en Turno Activo
            </div>
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-muted">Total de Turnos Activos Hoy:</span>
                    <strong class="font-monospace tabular-nums">{{ $summary['staff']['total'] }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-primary"><i class="bi bi-person-fill me-1"></i>Médicos en Turno Activo:</span>
                    <strong class="font-monospace tabular-nums text-primary">{{ $summary['staff']['medicos'] }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-info"><i class="bi bi-person-fill me-1"></i>Enfermeros en Turno Activo:</span>
                    <strong class="font-monospace tabular-nums text-info">{{ $summary['staff']['enfermeros'] }}</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-warning-emphasis"><i class="bi bi-person-exclamation me-1"></i>Pacientes NN Activos:</span>
                    <strong class="font-monospace tabular-nums text-warning-emphasis">{{ $summary['episodes']['nn_activos'] }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
