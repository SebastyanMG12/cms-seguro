@extends('layouts.app')

@section('title', 'Módulo de Urgencias - Hospital de Ubaté')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0 fw-bold text-dark">Servicio de Urgencias - Atención Clínica</h1>
        <p class="text-muted small mb-0">Gestión de episodios, clasificación de triage y censo de pacientes en urgencias.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="badge bg-primary-subtle text-primary border px-3 py-2 fs-6 tabular-nums">
            <i class="bi bi-person-badge me-1" aria-hidden="true"></i> Mis pacientes asignados: <strong>{{ $myActivePatientsCount }}</strong>
        </span>
        <a href="{{ route('preregistro.create') }}" class="btn btn-outline-dark btn-sm py-2 px-3 fw-semibold shadow-sm">
            <i class="bi bi-card-checklist me-1" aria-hidden="true"></i>Nuevo Pre-registro
        </a>
        @can('episodios.crear_nn')
            <a href="{{ route('episodes.nn.create') }}" class="btn btn-danger btn-sm py-2 px-3 fw-semibold shadow-sm">
                <i class="bi bi-person-exclamation me-1" aria-hidden="true"></i>Registrar Paciente NN
            </a>
        @endcan
    </div>
</div>


@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
        <i class="bi bi-check-circle me-1" aria-hidden="true"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

{{-- Pestañas de filtrado de episodios --}}
<ul class="nav nav-tabs mb-3">
    @if (auth()->user()->hasAnyRole(['medico', 'enfermero', 'coordinador']) || auth()->user()->staffProfile)
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'mis_pacientes' ? 'active fw-bold' : '' }}" href="{{ route('episodes.index', ['tab' => 'mis_pacientes']) }}">
                <i class="bi bi-person-check me-1" aria-hidden="true"></i> Mis Pacientes
                <span class="badge bg-primary ms-1 tabular-nums">{{ $counts['mis_pacientes'] }}</span>
            </a>
        </li>
    @endif
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'activos' ? 'active fw-bold' : '' }}" href="{{ route('episodes.index', ['tab' => 'activos']) }}">
            <i class="bi bi-activity me-1" aria-hidden="true"></i> Todos los Activos
            <span class="badge bg-danger ms-1 tabular-nums">{{ $counts['activos'] }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'en_espera' ? 'active fw-bold' : '' }}" href="{{ route('episodes.index', ['tab' => 'en_espera']) }}">
            <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i> En Espera de Triage
            <span class="badge bg-warning text-dark ms-1 tabular-nums">{{ $counts['en_espera'] }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'preregistrados' ? 'active fw-bold' : '' }}" href="{{ route('episodes.index', ['tab' => 'preregistrados']) }}">
            <i class="bi bi-card-checklist me-1" aria-hidden="true"></i> Pre-registros
            <span class="badge bg-secondary ms-1 tabular-nums">{{ $counts['preregistrados'] }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'egresados' ? 'active fw-bold' : '' }}" href="{{ route('episodes.index', ['tab' => 'egresados']) }}">
            <i class="bi bi-check2-all me-1" aria-hidden="true"></i> Egresados
            <span class="badge bg-light text-dark border ms-1 tabular-nums">{{ $counts['egresados'] }}</span>
        </a>
    </li>
</ul>

{{-- Listado de episodios en alta densidad --}}
<div class="card shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-compact">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Paciente</th>
                    <th>Documento</th>
                    <th>Triage</th>
                    <th>Estado</th>
                    <th>Ubicación / Camilla</th>
                    <th>Médico / Asistenciales</th>
                    <th>Llegada / Atención</th>
                    <th class="text-end">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($episodes as $ep)
                    <tr class="{{ $selectedId === $ep->id ? 'selected-patient' : '' }}">
                        <td>
                            <a href="{{ route('episodes.show', $ep) }}" class="fw-bold font-monospace tabular-nums text-decoration-none">
                                {{ $ep->public_code }}
                            </a>
                            @if ($ep->origin === 'pre_registro')
                                <span class="badge bg-light text-secondary border d-block mt-1 font-monospace" style="font-size: 0.7rem;">Pre-registro</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center flex-wrap gap-1">
                                <strong class="d-block">{{ $ep->patient?->full_name ?? 'Paciente no identificado' }}</strong>
                                @if ($ep->is_nn)
                                    @if ($ep->isReconciled())
                                        <span class="badge bg-success-subtle text-success border border-success small" title="Conciliado de {{ $ep->nn_code }}">
                                            <i class="bi bi-patch-check me-1" aria-hidden="true"></i>Conciliado
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger small">
                                            <i class="bi bi-person-fill-exclamation me-1" aria-hidden="true"></i>NN: {{ $ep->nn_code }}
                                        </span>
                                    @endif
                                @endif
                            </div>
                            <small class="text-muted text-truncate d-inline-block" style="max-width: 220px;" title="{{ $ep->reason }}">
                                {{ $ep->reason }}
                            </small>
                        </td>
                        <td class="small font-monospace tabular-nums">
                            @if ($ep->patient?->document_number)
                                {{ $ep->patient->document_type }}: {{ $ep->patient->document_number }}
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border">Sin documento</span>
                            @endif
                        </td>
                        <td>
                            @if ($ep->triage_level)
                                @php
                                    $triageClass = match($ep->triage_level) {
                                        'I' => 'triage-badge-1',
                                        'II' => 'triage-badge-2',
                                        'III' => 'triage-badge-3',
                                        'IV' => 'triage-badge-4',
                                        'V' => 'triage-badge-5',
                                        default => 'bg-secondary text-white',
                                    };
                                    $triageLabel = match($ep->triage_level) {
                                        'I' => 'Resucitación',
                                        'II' => 'Emergencia',
                                        'III' => 'Urgencia',
                                        'IV' => 'Prioritario',
                                        'V' => 'No urgente',
                                        default => '',
                                    };
                                @endphp
                                <span class="badge {{ $triageClass }} px-2 py-1">
                                    Nivel {{ $ep->triage_level }} - {{ $triageLabel }}
                                </span>
                            @else
                                <span class="badge bg-light text-muted border">Pendiente</span>
                            @endif
                        </td>
                        <td>
                            @switch($ep->status)
                                @case('preregistrado')
                                    <span class="badge bg-secondary"><i class="bi bi-clock me-1" aria-hidden="true"></i>Pre-registrado</span>
                                    @break
                                @case('en_espera')
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-top me-1" aria-hidden="true"></i>En espera</span>
                                    @break
                                @case('en_valoracion')
                                    <span class="badge bg-info text-dark"><i class="bi bi-clipboard2-pulse me-1" aria-hidden="true"></i>En valoración</span>
                                    @break
                                @case('ingresado')
                                    <span class="badge bg-primary"><i class="bi bi-hospital me-1" aria-hidden="true"></i>Ingresado</span>
                                    @break
                                @case('egresado')
                                    <span class="badge bg-success"><i class="bi bi-check-all me-1" aria-hidden="true"></i>Egresado</span>
                                    @break
                                @case('expirado')
                                    <span class="badge bg-dark"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Expirado</span>
                                    @break
                            @endswitch
                        </td>
                        <td>
                            @if ($ep->activeBedAssignment)
                                <span class="badge bg-success-subtle text-success border font-monospace tabular-nums">
                                    <i class="bi bi-door-closed me-1" aria-hidden="true"></i>{{ $ep->activeBedAssignment->bed?->code }}
                                </span>
                            @else
                                <span class="text-muted small">Sin camilla</span>
                            @endif
                        </td>
                        <td>
                            @if ($ep->responsibleUser)
                                <span class="small fw-semibold d-block">{{ $ep->responsibleUser->name }}</span>
                                <small class="text-muted font-monospace tabular-nums">{{ $ep->responsibleUser->staffProfile?->code }}</small>
                                @if ($ep->responsible_user_id === auth()->id())
                                    <span class="badge bg-primary-subtle text-primary border border-primary d-inline-block mt-1" style="font-size: 0.68rem;">
                                        <i class="bi bi-check2 me-1" aria-hidden="true"></i>Médico Responsable
                                    </span>
                                @endif
                            @else
                                <span class="text-muted small">Sin asignar</span>
                            @endif

                            @php
                                $mySupport = $ep->activeAssignments->firstWhere('user_id', auth()->id());
                            @endphp
                            @if ($mySupport && $ep->responsible_user_id !== auth()->id())
                                <span class="badge bg-info-subtle text-info-emphasis border border-info d-inline-block mt-1" style="font-size: 0.68rem;">
                                    <i class="bi bi-person-heart me-1" aria-hidden="true"></i>Tu rol: Apoyo
                                </span>
                            @endif
                        </td>
                        <td class="small tabular-nums">
                            @if ($ep->arrived_at)
                                <div>Llegada: {{ $ep->arrived_at->format('H:i') }}</div>
                            @endif
                            @if ($ep->admitted_at)
                                <div class="text-muted">Atención: {{ $ep->admitted_at->format('H:i') }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('episodes.show', $ep) }}" class="btn btn-sm btn-outline-primary py-0 px-2">
                                <i class="bi bi-folder2-open me-1" aria-hidden="true"></i> Historia
                            </a>
                            @if ($ep->isNn() && ! $ep->isReconciled())
                                @can('reconcile', $ep)
                                    <a href="{{ route('episodes.reconcile.create', $ep) }}" class="btn btn-sm btn-outline-warning py-0 px-2 mt-1 d-block text-nowrap" title="Conciliar identidad civil">
                                        <i class="bi bi-person-check me-1" aria-hidden="true"></i> Conciliar
                                    </a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-3 d-block mb-2" aria-hidden="true"></i>
                            No hay episodios de urgencias en este estado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($episodes->hasPages())
        <div class="card-footer bg-white py-2">
            {{ $episodes->links() }}
        </div>
    @endif
</div>
@endsection
