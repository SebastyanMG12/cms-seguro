@extends('layouts.app')

@section('title', 'Gestión de Personal Clínico y Turnos')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="text-decoration-none">Administración</a></li>
                <li class="breadcrumb-item active" aria-current="page">Personal y Turnos</li>
            </ol>
        </nav>
        <h1 class="h4 mb-0 fw-bold text-dark">
            <i class="bi bi-people-fill text-primary me-2" aria-hidden="true"></i>Personal Clínico y Turnos
        </h1>
        <p class="text-muted small mb-0">Gestión de profesionales asistenciales, disponibilidad de servicio y programación de turnos de urgencias.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="#card-registrar-personal" class="btn btn-outline-primary btn-sm d-flex align-items-center">
            <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Nuevo Profesional
        </a>
        <a href="#card-programar-turno" class="btn btn-primary btn-sm d-flex align-items-center">
            <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Programar Turno
        </a>
    </div>
</div>

{{-- Mensajes de estado y validación --}}
@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2 px-3 small d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
        <div>{{ session('success') }}</div>
        <button type="button" class="btn-close py-2 px-3" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show py-2 px-3 small" role="alert">
        <div class="fw-bold mb-1 d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>No se pudo procesar la solicitud:
        </div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close py-2 px-3" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

{{-- Tarjetas de resumen operacional (DISEÑO_FRONTEND.md sección 2.5) --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase mb-1">Total Personal</div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="fs-4 fw-bold tabular-nums text-dark">{{ $totalStaff }}</span>
                    <i class="bi bi-people text-primary fs-4" aria-hidden="true"></i>
                </div>
                <small class="text-muted">Perfiles clínicos creados</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase mb-1">En Turno Activo</div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="fs-4 fw-bold tabular-nums text-success">{{ $activeShiftsCount }}</span>
                    <i class="bi bi-play-circle text-success fs-4" aria-hidden="true"></i>
                </div>
                <small class="text-muted">Disponibles para asignación</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase mb-1">En Descanso</div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="fs-4 fw-bold tabular-nums text-warning">{{ $onRestCount }}</span>
                    <i class="bi bi-pause-circle text-warning fs-4" aria-hidden="true"></i>
                </div>
                <small class="text-muted">No asignables temporalmente</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm border h-100">
            <div class="card-body p-3">
                <div class="text-muted small fw-semibold text-uppercase mb-1">Pacientes Asignados</div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="fs-4 fw-bold tabular-nums text-info-emphasis">{{ $totalActivePatients }}</span>
                    <i class="bi bi-person-lines-fill text-info fs-4" aria-hidden="true"></i>
                </div>
                <small class="text-muted">Casos activos en urgencias</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Listados Principales --}}
    <div class="col-lg-8">
        {{-- Tabla de Personal Asistencial --}}
        <div class="card shadow-sm border mb-3">
            <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0 fw-bold text-dark">
                    <i class="bi bi-person-badge me-2 text-primary" aria-hidden="true"></i>Plantilla de Profesionales Clínicos
                </h2>
                <span class="badge bg-secondary-subtle text-secondary border small tabular-nums">{{ $staff->total() }} registrados</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-compact">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Código</th>
                            <th scope="col">Profesional</th>
                            <th scope="col">Rol / Especialidad</th>
                            <th scope="col">Turno Actual</th>
                            <th scope="col">Pacientes</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($staff as $p)
                            <tr>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace tabular-nums">{{ $p->code }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $p->user->name }}</div>
                                    <small class="text-muted d-block">{{ $p->user->email }}</small>
                                </td>
                                <td>
                                    <div class="mb-1">
                                        @foreach ($p->user->roles as $role)
                                            <span class="badge bg-primary-subtle text-primary border me-1">{{ ucfirst($role->name) }}</span>
                                        @endforeach
                                    </div>
                                    <small class="text-secondary fw-semibold">{{ $p->specialty }}</small>
                                </td>
                                <td>
                                    @if ($p->active_shift)
                                        <span class="badge bg-success-subtle text-success border border-success tabular-nums">
                                            <i class="bi bi-clock-fill me-1" aria-hidden="true"></i>En turno ({{ ucfirst($p->active_shift->type) }})
                                        </span>
                                        <small class="text-muted d-block tabular-nums">
                                            Hasta {{ $p->active_shift->ends_at->format('H:i') }}
                                        </small>
                                    @else
                                        <span class="badge bg-light text-secondary border">
                                            <i class="bi bi-dash-circle me-1" aria-hidden="true"></i>Sin turno activo
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($p->active_patients_count > 0)
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 tabular-nums" data-bs-toggle="modal" data-bs-target="#modalStaffPatients{{ $p->id }}" title="Ver los {{ $p->active_patients_count }} pacientes atendidos por {{ $p->user->name }}">
                                            <i class="bi bi-people-fill me-1" aria-hidden="true"></i>{{ $p->active_patients_count }} <span class="d-none d-md-inline small">atendiendo</span>
                                        </button>
                                    @else
                                        <span class="badge bg-light text-muted border tabular-nums" title="Sin pacientes activos asignados">
                                            <i class="bi bi-person me-1" aria-hidden="true"></i>0 asignados
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @switch($p->status)
                                        @case('activo')
                                            <span class="badge bg-success-subtle text-success border border-success">
                                                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Activo
                                            </span>
                                            @break
                                        @case('en_descanso')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning">
                                                <i class="bi bi-pause-circle me-1" aria-hidden="true"></i>En descanso
                                            </span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Inactivo
                                            </span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de personal">
                                        <a href="{{ route('staff.edit', $p) }}" class="btn btn-outline-primary btn-sm py-1 px-2" title="Editar profesional y especialidad">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                            <span class="visually-hidden">Editar</span>
                                        </a>

                                        @if ($p->status === 'activo')
                                            <form method="POST" action="{{ route('staff.update-status', $p) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="status" value="en_descanso">
                                                <button type="submit" class="btn btn-outline-warning btn-sm py-1 px-2 ms-1" title="Poner en descanso temporal">
                                                    <i class="bi bi-pause" aria-hidden="true"></i>
                                                    <span class="visually-hidden">Poner en descanso</span>
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('staff.update-status', $p) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="status" value="activo">
                                                <button type="submit" class="btn btn-outline-success btn-sm py-1 px-2 ms-1" title="Activar disponibilidad">
                                                    <i class="bi bi-play" aria-hidden="true"></i>
                                                    <span class="visually-hidden">Activar</span>
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('staff.destroy', $p) }}" class="d-inline" onsubmit="return confirm('¿Desactivar el perfil asistencial de este profesional?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2 ms-1" title="Desactivar perfil asistencial">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                                <span class="visually-hidden">Desactivar</span>
                                            </button>
                                        </form>
                                    </div>

                                    @if ($p->active_patients_count > 0)
                                        {{-- Modal de Pacientes Asignados al Profesional --}}
                                        <div class="modal fade text-start" id="modalStaffPatients{{ $p->id }}" tabindex="-1" aria-labelledby="modalStaffPatientsLabel{{ $p->id }}" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header py-2 px-3 bg-light">
                                                        <div>
                                                            <h3 class="modal-title h6 fw-bold text-dark mb-0" id="modalStaffPatientsLabel{{ $p->id }}">
                                                                <i class="bi bi-person-lines-fill text-primary me-2" aria-hidden="true"></i>Pacientes a cargo de: {{ $p->user->name }}
                                                            </h3>
                                                            <small class="text-muted">
                                                                Código: <span class="font-monospace fw-semibold">{{ $p->code }}</span> | Especialidad: {{ $p->specialty }} |
                                                                Turno: {{ $p->active_shift ? ucfirst($p->active_shift->type) . ' (activo)' : 'Sin turno activo' }}
                                                            </small>
                                                        </div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                    </div>
                                                    <div class="modal-body p-0">
                                                        <div class="table-responsive">
                                                            <table class="table table-hover align-middle mb-0 table-compact small">
                                                                <thead class="table-light">
                                                                    <tr>
                                                                        <th>Episodio</th>
                                                                        <th>Paciente</th>
                                                                        <th>Documento</th>
                                                                        <th>Rol Asistencial</th>
                                                                        <th>Triage</th>
                                                                        <th>Estado</th>
                                                                        <th>Camilla</th>
                                                                        <th>Desde</th>
                                                                        <th class="text-end">Historia</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($p->active_assignments as $asg)
                                                                        @php $ep = $asg->episode; @endphp
                                                                        <tr>
                                                                            <td>
                                                                                <a href="{{ route('episodes.show', $ep) }}" class="fw-bold font-monospace tabular-nums text-decoration-none">
                                                                                    {{ $ep->public_code }}
                                                                                </a>
                                                                                @if ($ep->is_nn)
                                                                                    <span class="badge bg-danger-subtle text-danger border border-danger d-block font-monospace" style="font-size: 0.65rem;">
                                                                                        NN: {{ $ep->nn_code }}
                                                                                    </span>
                                                                                @endif
                                                                            </td>
                                                                            <td>
                                                                                <strong class="d-block">{{ $ep->patient?->full_name ?? 'Paciente sin identificar' }}</strong>
                                                                                <small class="text-muted text-truncate d-inline-block" style="max-width: 170px;" title="{{ $ep->reason }}">
                                                                                    {{ $ep->reason }}
                                                                                </small>
                                                                            </td>
                                                                            <td class="font-monospace tabular-nums">
                                                                                @if ($ep->patient?->document_number)
                                                                                    {{ $ep->patient->document_type }}: {{ $ep->patient->document_number }}
                                                                                @else
                                                                                    <span class="badge bg-secondary-subtle text-secondary border">Sin doc</span>
                                                                                @endif
                                                                            </td>
                                                                            <td>
                                                                                @if ($asg->role === 'responsable')
                                                                                    <span class="badge bg-primary-subtle text-primary border border-primary">
                                                                                        <i class="bi bi-star-fill me-1" aria-hidden="true"></i>Responsable
                                                                                    </span>
                                                                                @else
                                                                                    <span class="badge bg-info-subtle text-info-emphasis border border-info">
                                                                                        <i class="bi bi-hand-thumbs-up me-1" aria-hidden="true"></i>Apoyo
                                                                                    </span>
                                                                                @endif
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
                                                                                    <span class="text-muted">Pendiente</span>
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
                                                                            <td class="tabular-nums">
                                                                                {{ $asg->started_at?->format('H:i') ?? 'N/D' }}
                                                                            </td>
                                                                            <td class="text-end">
                                                                                <a href="{{ route('episodes.show', $ep) }}" class="btn btn-sm btn-outline-primary py-0 px-2" title="Abrir historia clínica">
                                                                                    <i class="bi bi-folder2-open" aria-hidden="true"></i>
                                                                                </a>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer py-1 px-3">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-person-x fs-3 d-block mb-1" aria-hidden="true"></i>
                                    No hay perfiles de personal asistencial registrados en el sistema.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($staff->hasPages())
                <div class="card-footer bg-white py-2 px-3">
                    {{ $staff->links() }}
                </div>
            @endif
        </div>

        {{-- Tabla de Turnos Asistenciales --}}
        <div class="card shadow-sm border mb-3">
            <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0 fw-bold text-dark">
                    <i class="bi bi-clock-history me-2 text-primary" aria-hidden="true"></i>Programación de Turnos Clínicos
                </h2>
                <span class="badge bg-secondary-subtle text-secondary border small tabular-nums">{{ $shifts->total() }} turnos</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-compact">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Profesional</th>
                            <th scope="col">Tipo de Turno</th>
                            <th scope="col">Inicio</th>
                            <th scope="col">Fin</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shifts as $shift)
                            <tr>
                                <td>
                                    <strong class="text-dark">{{ $shift->user->name }}</strong>
                                    <small class="text-muted d-block font-monospace tabular-nums">
                                        {{ $shift->user->staffProfile?->code ?? 'Sin código' }}
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ ucfirst($shift->type) }}</span>
                                </td>
                                <td class="small tabular-nums">{{ $shift->starts_at->format('d/m/Y H:i') }}</td>
                                <td class="small tabular-nums">{{ $shift->ends_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    @switch($shift->status)
                                        @case('activo')
                                            <span class="badge bg-success-subtle text-success border border-success">
                                                <i class="bi bi-play-fill me-1" aria-hidden="true"></i>En curso
                                            </span>
                                            @break
                                        @case('programado')
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info">
                                                <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Programado
                                            </span>
                                            @break
                                        @case('finalizado')
                                            <span class="badge bg-light text-secondary border">
                                                <i class="bi bi-check-all me-1" aria-hidden="true"></i>Finalizado
                                            </span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary-subtle text-secondary border">
                                                {{ ucfirst($shift->status) }}
                                            </span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        @if ($shift->status === 'programado')
                                            <form method="POST" action="{{ route('shifts.start', $shift) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success btn-sm py-1 px-2" title="Iniciar turno inmediatamente">
                                                    <i class="bi bi-play-circle me-1" aria-hidden="true"></i>Iniciar
                                                </button>
                                            </form>
                                        @elseif ($shift->status === 'activo')
                                            <form method="POST" action="{{ route('shifts.end', $shift) }}" class="d-inline" onsubmit="return confirm('¿Finalizar el turno activo de este profesional? Asegúrese de que no tenga pacientes pendientes de reasignar.');">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" title="Finalizar turno">
                                                    <i class="bi bi-stop-circle me-1" aria-hidden="true"></i>Finalizar
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-calendar-x fs-3 d-block mb-1" aria-hidden="true"></i>
                                    No hay turnos registrados en el sistema.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($shifts->hasPages())
                <div class="card-footer bg-white py-2 px-3">
                    {{ $shifts->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Columna Lateral: Formularios de Gestión --}}
    <div class="col-lg-4">
        {{-- Registrar Personal Clínico --}}
        <div class="card shadow-sm border mb-3" id="card-registrar-personal">
            <div class="card-header bg-primary text-white py-2 px-3">
                <h3 class="h6 mb-0 fw-bold">
                    <i class="bi bi-person-plus-fill me-2" aria-hidden="true"></i>Registrar Personal Clínico
                </h3>
            </div>
            <div class="card-body p-3">
                {{-- Pestañas para elegir modo de registro --}}
                <ul class="nav nav-pills nav-fill mb-3" id="pills-tab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active py-1 small" id="tab-nuevo-usuario" data-bs-toggle="pill" data-bs-target="#panel-nuevo-usuario" type="button" role="tab" aria-controls="panel-nuevo-usuario" aria-selected="true">
                            <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Crear Nuevo
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link py-1 small" id="tab-vincular-usuario" data-bs-toggle="pill" data-bs-target="#panel-vincular-usuario" type="button" role="tab" aria-controls="panel-vincular-usuario" aria-selected="false">
                            <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Vincular Existente
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="pills-tabContent">
                    {{-- Formulario 1: Crear nuevo usuario y perfil clínico directamente --}}
                    <div class="tab-pane fade show active" id="panel-nuevo-usuario" role="tabpanel" aria-labelledby="tab-nuevo-usuario">
                        <form method="POST" action="{{ route('staff.store-user') }}">
                            @csrf
                            <div class="mb-2">
                                <label for="name" class="form-label small fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="name" name="name" placeholder="Dr. Carlos Mendoza" required value="{{ old('name') }}">
                            </div>

                            <div class="mb-2">
                                <label for="email" class="form-label small fw-semibold">Correo Electrónico <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-sm" id="email" name="email" placeholder="carlos.mendoza@hospital.gov.co" required value="{{ old('email') }}">
                            </div>

                            <div class="mb-2">
                                <label for="password" class="form-label small fw-semibold">Contraseña Temporal <span class="text-danger">*</span></label>
                                <input type="password" class="form-control form-control-sm" id="password" name="password" placeholder="Mínimo 8 caracteres" required>
                                <div class="form-text small">El usuario deberá configurar 2FA en su primer acceso.</div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-semibold d-block">Rol en Urgencias <span class="text-danger">*</span></label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="roles[]" id="role_medico" value="medico" checked>
                                    <label class="form-check-label small" for="role_medico">Médico</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="roles[]" id="role_enfermero" value="enfermero">
                                    <label class="form-check-label small" for="role_enfermero">Enfermero</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="roles[]" id="role_coordinador" value="coordinador">
                                    <label class="form-check-label small" for="role_coordinador">Coordinador</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="specialty" class="form-label small fw-semibold">Especialidad / Cargo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="specialty" name="specialty" placeholder="Ej: Médico Urgenciólogo, Pediatra, Enfermera Jefe" required value="{{ old('specialty') }}">
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100 py-2">
                                <i class="bi bi-save me-1" aria-hidden="true"></i>Registrar Profesional
                            </button>
                        </form>
                    </div>

                    {{-- Formulario 2: Vincular usuario ya existente en el sistema --}}
                    <div class="tab-pane fade" id="panel-vincular-usuario" role="tabpanel" aria-labelledby="tab-vincular-usuario">
                        <form method="POST" action="{{ route('staff.store') }}">
                            @csrf
                            <div class="mb-2">
                                <label for="user_id" class="form-label small fw-semibold">Usuario del Sistema <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="user_id" name="user_id" required>
                                    <option value="">-- Seleccionar Usuario sin perfil --</option>
                                    @forelse ($availableUsers as $user)
                                        <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }} ({{ $user->email }})
                                        </option>
                                    @empty
                                        <option value="" disabled>No hay usuarios asistenciales pendientes por vincular</option>
                                    @endforelse
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="specialty_existing" class="form-label small fw-semibold">Especialidad / Cargo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="specialty_existing" name="specialty" placeholder="Ej: Médico Urgenciólogo" required value="{{ old('specialty') }}">
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100 py-2" {{ $availableUsers->isEmpty() ? 'disabled' : '' }}>
                                <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Vincular Perfil Asistencial
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- Programar Turno --}}
        <div class="card shadow-sm border mb-3" id="card-programar-turno">
            <div class="card-header bg-dark text-white py-2 px-3">
                <h3 class="h6 mb-0 fw-bold">
                    <i class="bi bi-calendar-plus-fill me-2" aria-hidden="true"></i>Programar Turno Clínico
                </h3>
            </div>
            <div class="card-body p-3">
                <form method="POST" action="{{ route('shifts.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label for="shift_user_id" class="form-label small fw-semibold">Profesional <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="shift_user_id" name="user_id" required>
                            <option value="">-- Seleccionar Profesional --</option>
                            @foreach ($staff as $p)
                                <option value="{{ $p->user_id }}">
                                    {{ $p->user->name }} ({{ $p->code }} - {{ $p->specialty }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="type" class="form-label small fw-semibold">Tipo de Turno <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="type" name="type" required>
                            <option value="diurno">Diurno</option>
                            <option value="nocturno">Nocturno</option>
                            <option value="rotativo">Rotativo</option>
                            <option value="guardia">Guardia</option>
                            <option value="disponibilidad">Disponibilidad</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="starts_at" class="form-label small fw-semibold">Inicio del Turno <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control form-control-sm" id="starts_at" name="starts_at" required value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>

                    <div class="mb-3">
                        <label for="ends_at" class="form-label small fw-semibold">Fin del Turno <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control form-control-sm" id="ends_at" name="ends_at" required value="{{ now()->addHours(12)->format('Y-m-d\TH:i') }}">
                    </div>

                    <button type="submit" class="btn btn-dark btn-sm w-100 py-2">
                        <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Programar Turno
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
