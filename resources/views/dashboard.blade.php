@extends('layouts.app')

@section('title', 'Panel de Control - Hospital de Ubaté')

@section('content')
<div class="row g-4">
    <aside class="col-md-3">
        @include('partials.sidebar')
    </aside>

    <section class="col-md-9">
        @if ($isMedicalStaff)
            {{-- ENCABEZADO DE ESTACIÓN CLÍNICA DEL PROFESIONAL --}}
            <div class="card shadow-sm mb-3 border">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                <h1 class="h4 mb-0 fw-bold text-dark">{{ auth()->user()->name }}</h1>
                                @if ($staffProfile)
                                    <span class="badge bg-primary font-monospace tabular-nums">{{ $staffProfile->code }}</span>
                                    <span class="badge bg-light text-dark border">{{ $staffProfile->specialty }}</span>
                                @endif
                            </div>
                            <p class="text-muted small mb-0">Estación Clínica de Urgencias - Hospital de Ubaté</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            @if ($activeShift)
                                <span class="badge bg-success-subtle text-success border border-success px-3 py-2">
                                    <i class="bi bi-clock-history me-1" aria-hidden="true"></i>
                                    Turno Activo: <strong class="tabular-nums">{{ $activeShift->starts_at?->format('H:i') }} - {{ $activeShift->ends_at?->format('H:i') }}</strong>
                                </span>
                            @elseif ($nextShift)
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2">
                                    <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>
                                    Próximo turno: <span class="tabular-nums">{{ $nextShift->starts_at?->format('d/m H:i') }}</span>
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border px-3 py-2">
                                    <i class="bi bi-pause-circle me-1" aria-hidden="true"></i> Sin turno activo
                                </span>
                            @endif
                            <a href="{{ route('episodes.index') }}" class="btn btn-primary btn-sm">
                                <i class="bi bi-hospital me-1" aria-hidden="true"></i> Módulo Urgencias
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- MÉTRICAS CLÍNICAS DEL PROFESIONAL --}}
            <div class="row g-2 mb-3">
                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small fw-semibold">Mis Pacientes</span>
                                <i class="bi bi-people text-primary fs-5" aria-hidden="true"></i>
                            </div>
                            <div class="fs-3 fw-bold tabular-nums text-dark mt-1">{{ $myPatientsCount }}</div>
                            <span class="text-muted small">En atención actual</span>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small fw-semibold">Como Responsable</span>
                                <i class="bi bi-heart-pulse text-danger fs-5" aria-hidden="true"></i>
                            </div>
                            <div class="fs-3 fw-bold tabular-nums text-dark mt-1">{{ $asResponsibleCount }}</div>
                            <span class="text-muted small">Médico tratante principal</span>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small fw-semibold">Como Apoyo</span>
                                <i class="bi bi-bandaid text-info fs-5" aria-hidden="true"></i>
                            </div>
                            <div class="fs-3 fw-bold tabular-nums text-dark mt-1">{{ $asSupportCount }}</div>
                            <span class="text-muted small">Personal de apoyo</span>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small fw-semibold">Triage Crítico (I - II)</span>
                                <i class="bi bi-exclamation-triangle text-warning fs-5" aria-hidden="true"></i>
                            </div>
                            <div class="fs-3 fw-bold tabular-nums {{ $criticalPatientsCount > 0 ? 'text-danger' : 'text-dark' }} mt-1">
                                {{ $criticalPatientsCount }}
                            </div>
                            <span class="text-muted small">Resucitación o emergencia</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TABLA DE PACIENTES ASIGNADOS AL PROFESIONAL --}}
            <div class="card shadow-sm mb-3 border">
                <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-clipboard2-pulse me-1 text-primary" aria-hidden="true"></i>
                        Pacientes en Atención Clínica Asignados
                    </h2>
                    <span class="badge bg-primary-subtle text-primary border border-primary tabular-nums">
                        {{ $activeAssignments->count() }} paciente(s)
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-compact">
                        <thead class="table-light">
                            <tr>
                                <th>Episodio</th>
                                <th>Paciente</th>
                                <th>Triage</th>
                                <th>Estado</th>
                                <th>Camilla</th>
                                <th>Tu Rol</th>
                                <th>Inicio</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activeAssignments as $assignment)
                                @php $ep = $assignment->episode; @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('episodes.show', $ep) }}" class="fw-bold font-monospace tabular-nums text-decoration-none">
                                            {{ $ep->public_code }}
                                        </a>
                                        @if ($ep->is_nn)
                                            <span class="badge bg-danger-subtle text-danger border border-danger d-block mt-1 font-monospace" style="font-size: 0.65rem;">
                                                NN: {{ $ep->nn_code }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fw-semibold">{{ $ep->patient?->full_name ?? 'Paciente no identificado' }}</div>
                                        <small class="text-muted font-monospace tabular-nums">
                                            {{ $ep->patient?->document_type ?? 'DOC' }}: {{ $ep->patient?->document_number ? substr($ep->patient->document_number, 0, 3) . '***' . substr($ep->patient->document_number, -2) : 'Sin documento' }}
                                        </small>
                                    </td>
                                    <td>
                                        @if ($ep->triage_level)
                                            <span class="badge {{ match($ep->triage_level) {
                                                'I' => 'bg-danger text-white',
                                                'II' => 'bg-warning text-dark',
                                                'III' => 'bg-warning-subtle text-warning-emphasis border border-warning',
                                                'IV' => 'bg-success-subtle text-success border border-success',
                                                'V' => 'bg-info-subtle text-info-emphasis border border-info',
                                                default => 'bg-secondary text-white'
                                            } }} tabular-nums">
                                                Nivel {{ $ep->triage_level }}
                                            </span>
                                        @else
                                            <span class="text-muted small">Pendiente</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ match($ep->status) {
                                            'en_valoracion' => 'bg-info text-dark',
                                            'ingresado' => 'bg-primary text-white',
                                            default => 'bg-secondary text-white'
                                        } }}">
                                            {{ ucfirst(str_replace('_', ' ', $ep->status)) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($ep->activeBedAssignment)
                                            <span class="badge bg-success-subtle text-success border border-success font-monospace tabular-nums">
                                                <i class="bi bi-door-closed me-1" aria-hidden="true"></i>{{ $ep->activeBedAssignment->bed->code }}
                                            </span>
                                        @else
                                            <span class="text-muted small">Sin camilla</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($assignment->role === 'responsable')
                                            <span class="badge bg-primary text-white">Médico Responsable</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary border">Apoyo Clínico</span>
                                        @endif
                                    </td>
                                    <td class="tabular-nums small">
                                        {{ $assignment->started_at?->format('H:i') ?? 'N/D' }}
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('episodes.show', $ep) }}" class="btn btn-sm btn-outline-primary py-1 px-2" title="Abrir historia clínica">
                                            <i class="bi bi-folder2-open me-1" aria-hidden="true"></i>Historia
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bi bi-clipboard2-check text-muted fs-3 d-block mb-2" aria-hidden="true"></i>
                                        <div class="fw-semibold">No tiene pacientes en atención en este momento</div>
                                        <small class="d-block mb-3">Puede seleccionar un paciente en espera de atención desde el tablero de urgencias.</small>
                                        <a href="{{ route('episodes.index') }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>Ir al Módulo de Urgencias
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ESTADO OPERATIVO GENERAL DEL SERVICIO DE URGENCIAS --}}
            <div class="card shadow-sm border">
                <div class="card-header bg-light py-2 px-3">
                    <h2 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-hospital me-1 text-primary" aria-hidden="true"></i>
                        Censo General del Servicio de Urgencias
                    </h2>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3 text-center">
                        <div class="col-md-4">
                            <div class="p-2 border rounded bg-light">
                                <span class="text-muted small d-block">En Espera de Triage</span>
                                <span class="fs-4 fw-bold tabular-nums text-warning-emphasis">{{ $hospitalWaitingCount }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2 border rounded bg-light">
                                <span class="text-muted small d-block">Pacientes Activos en Urgencias</span>
                                <span class="fs-4 fw-bold tabular-nums text-primary">{{ $hospitalActiveCount }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-2 border rounded bg-light">
                                <span class="text-muted small d-block">Camillas Disponibles</span>
                                <span class="fs-4 fw-bold tabular-nums text-success">{{ $availableBedsCount }} / {{ $totalBedsCount }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        @else
            {{-- PANEL DE CONTROL INSTITUCIONAL Y ADMINISTRATIVO --}}
            <div class="card shadow-sm mb-3 border">
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h1 class="h4 mb-0 fw-bold text-dark">Panel de Control Operativo</h1>
                            <p class="text-muted small mb-0">Gestión de operaciones hospitalarias, recursos y personal - Hospital de Ubaté</p>
                        </div>
                        <div class="d-flex gap-2">
                            @can('ocupacion.ver')
                                <a href="{{ route('occupancy.index') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i> Censo de Camillas
                                </a>
                            @endcan
                            @can('personal.gestionar')
                                <a href="{{ route('staff.index') }}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-people me-1" aria-hidden="true"></i> Personal y Turnos
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>

            {{-- MÉTRICAS GENERALES DE URGENCIAS Y OPERACIONES --}}
            <div class="row g-2 mb-3">
                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small fw-semibold">Pacientes Activos</span>
                                <i class="bi bi-activity text-danger fs-5" aria-hidden="true"></i>
                            </div>
                            <div class="fs-3 fw-bold tabular-nums text-dark mt-1">{{ $hospitalActiveCount }}</div>
                            <span class="text-muted small">En valoración o ingresados</span>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small fw-semibold">En Espera de Triage</span>
                                <i class="bi bi-hourglass-split text-warning fs-5" aria-hidden="true"></i>
                            </div>
                            <div class="fs-3 fw-bold tabular-nums text-warning-emphasis mt-1">{{ $hospitalWaitingCount }}</div>
                            <span class="text-muted small">Llegada confirmada</span>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small fw-semibold">Camillas Ocupadas</span>
                                <i class="bi bi-door-closed text-primary fs-5" aria-hidden="true"></i>
                            </div>
                            <div class="fs-3 fw-bold tabular-nums text-primary mt-1">{{ $occupiedBedsCount }}</div>
                            <span class="text-muted small">Disponibles: <strong class="tabular-nums text-success">{{ $availableBedsCount }}</strong></span>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card shadow-sm border h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small fw-semibold">Personal en Turno</span>
                                <i class="bi bi-person-badge text-success fs-5" aria-hidden="true"></i>
                            </div>
                            <div class="fs-3 fw-bold tabular-nums text-success mt-1">{{ $activeShiftsCount }}</div>
                            <span class="text-muted small">De {{ $totalStaffCount }} profesionales</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ACCESOS DIRECTOS OPERATIVOS --}}
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card shadow-sm border h-100">
                        <div class="card-header bg-light py-2 px-3">
                            <h2 class="h6 mb-0 fw-bold text-dark">
                                <i class="bi bi-hospital me-1 text-primary" aria-hidden="true"></i>
                                Flujo de Pacientes
                            </h2>
                        </div>
                        <div class="card-body p-3">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span>Pre-registros vigentes</span>
                                    <span class="badge bg-secondary font-monospace tabular-nums">{{ $hospitalPreregCount }}</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span>Pacientes en espera</span>
                                    <span class="badge bg-warning text-dark font-monospace tabular-nums">{{ $hospitalWaitingCount }}</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span>Pacientes activos en atención</span>
                                    <span class="badge bg-primary font-monospace tabular-nums">{{ $hospitalActiveCount }}</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span>Egresos registrados</span>
                                    <span class="badge bg-success font-monospace tabular-nums">{{ $hospitalDischargedCount }}</span>
                                </li>
                            </ul>
                            <div class="mt-3">
                                <a href="{{ route('episodes.index') }}" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i> Ir al Tablero de Urgencias
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow-sm border h-100">
                        <div class="card-header bg-light py-2 px-3">
                            <h2 class="h6 mb-0 fw-bold text-dark">
                                <i class="bi bi-sliders me-1 text-primary" aria-hidden="true"></i>
                                Administración y Recursos
                            </h2>
                        </div>
                        <div class="card-body p-3">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span>Camillas en mantenimiento / FDS</span>
                                    <span class="badge bg-warning-subtle text-warning-emphasis font-monospace tabular-nums">{{ $maintenanceBedsCount }}</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span>Plantilla asistencial registrada</span>
                                    <span class="badge bg-info-subtle text-info-emphasis font-monospace tabular-nums">{{ $totalStaffCount }}</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                                    <span>Usuarios del sistema</span>
                                    <span class="badge bg-light text-dark border font-monospace tabular-nums">{{ $totalUsersCount }}</span>
                                </li>
                            </ul>
                            <div class="d-flex gap-2 mt-3">
                                <a href="{{ route('occupancy.index') }}" class="btn btn-outline-primary btn-sm flex-fill">
                                    <i class="bi bi-door-open me-1" aria-hidden="true"></i> Censo Camillas
                                </a>
                                <a href="{{ route('staff.index') }}" class="btn btn-outline-primary btn-sm flex-fill">
                                    <i class="bi bi-person-lines-fill me-1" aria-hidden="true"></i> Turnos y Personal
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </section>
</div>
@endsection