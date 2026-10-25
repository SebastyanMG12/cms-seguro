@extends('layouts.app')

@section('title', 'Gestión de Personal y Turnos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0 fw-bold">Personal Asistencial y Turnos</h1>
        <p class="text-muted small mb-0">Administración de perfiles clínicos, disponibilidad y programación de turnos.</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger py-2" role="alert">
        <ul class="mb-0 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    {{-- Listado de Personal --}}
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0 fw-bold text-dark"><i class="bi bi-people me-1" aria-hidden="true"></i>Plantilla de Profesionales</h2>
                <span class="badge bg-secondary small tabular-nums">{{ $staff->count() }} Registrados</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-compact">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Profesional</th>
                            <th>Rol / Especialidad</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($staff as $p)
                            <tr>
                                <td><span class="badge bg-secondary font-monospace tabular-nums">{{ $p->code }}</span></td>
                                <td>
                                    <strong class="d-block">{{ $p->user->name }}</strong>
                                    <small class="text-muted">{{ $p->user->email }}</small>
                                </td>
                                <td>
                                    <div class="mb-1">
                                        @foreach ($p->user->roles as $role)
                                            <span class="badge bg-primary-subtle text-primary border me-1">{{ $role->name }}</span>
                                        @endforeach
                                    </div>
                                    <small class="text-muted">{{ $p->specialty }}</small>
                                </td>
                                <td>
                                    @switch($p->status)
                                        @case('activo')
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Activo</span>
                                            @break
                                        @case('en_descanso')
                                            <span class="badge bg-warning text-dark"><i class="bi bi-pause-circle me-1" aria-hidden="true"></i>En descanso</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Inactivo</span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if ($p->status === 'activo')
                                            <form method="POST" action="{{ route('staff.update-status', $p) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="en_descanso">
                                                <button type="submit" class="btn btn-outline-warning btn-sm py-0 px-2" title="Poner en descanso">
                                                    <i class="bi bi-pause me-1" aria-hidden="true"></i>Descanso
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('staff.update-status', $p) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="activo">
                                                <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2" title="Activar profesional">
                                                    <i class="bi bi-play me-1" aria-hidden="true"></i>Activar
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('staff.destroy', $p) }}" onsubmit="return confirm('¿Desactivar este perfil de personal?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2 ms-1" title="Desactivar perfil">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted">No hay perfiles de personal registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Turnos Recientes / Activos --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2 px-3">
                <h2 class="h6 mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Turnos Programados y Activos</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-compact">
                    <thead class="table-light">
                        <tr>
                            <th>Profesional</th>
                            <th>Tipo</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shifts as $shift)
                            <tr>
                                <td>
                                    <strong>{{ $shift->user->name }}</strong>
                                    <small class="text-muted d-block">{{ $shift->user->staffProfile?->code }}</small>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ ucfirst($shift->type) }}</span></td>
                                <td class="small tabular-nums">{{ $shift->starts_at->format('d/m/Y H:i') }}</td>
                                <td class="small tabular-nums">{{ $shift->ends_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    @switch($shift->status)
                                        @case('activo')
                                            <span class="badge bg-success"><i class="bi bi-play-fill me-1" aria-hidden="true"></i>Activo</span>
                                            @break
                                        @case('programado')
                                            <span class="badge bg-info text-dark"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>Programado</span>
                                            @break
                                        @case('finalizado')
                                            <span class="badge bg-secondary"><i class="bi bi-check-all me-1" aria-hidden="true"></i>Finalizado</span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if ($shift->status === 'programado')
                                            <form method="POST" action="{{ route('shifts.start', $shift) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2">
                                                    <i class="bi bi-play-circle me-1" aria-hidden="true"></i>Iniciar
                                                </button>
                                            </form>
                                        @elseif ($shift->status === 'activo')
                                            <form method="POST" action="{{ route('shifts.end', $shift) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">
                                                    <i class="bi bi-stop-circle me-1" aria-hidden="true"></i>Finalizar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">No hay turnos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Formularios Laterales --}}
    <div class="col-lg-4">
        {{-- Registrar Perfil de Personal --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white py-2 px-3">
                <h3 class="h6 mb-0 fw-bold"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Registrar Perfil Clínico</h3>
            </div>
            <div class="card-body p-3">
                <form method="POST" action="{{ route('staff.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label for="user_id" class="form-label small fw-semibold">Usuario del Sistema</label>
                        <select class="form-select form-select-sm" id="user_id" name="user_id" required>
                            <option value="">-- Seleccionar Usuario --</option>
                            @foreach ($availableUsers as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="specialty" class="form-label small fw-semibold">Especialidad / Cargo</label>
                        <input type="text" class="form-control form-control-sm" id="specialty" name="specialty" placeholder="Ej: Médico Urgenciólogo, Enfermera Jefe" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-save me-1" aria-hidden="true"></i>Crear Perfil Asistencial
                    </button>
                </form>
            </div>
        </div>

        {{-- Programar Turno --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-dark text-white py-2 px-3">
                <h3 class="h6 mb-0 fw-bold"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Programar Turno</h3>
            </div>
            <div class="card-body p-3">
                <form method="POST" action="{{ route('shifts.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label for="shift_user_id" class="form-label small fw-semibold">Profesional</label>
                        <select class="form-select form-select-sm" id="shift_user_id" name="user_id" required>
                            <option value="">-- Seleccionar Profesional --</option>
                            @foreach ($staff as $p)
                                <option value="{{ $p->user_id }}">{{ $p->user->name }} ({{ $p->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="type" class="form-label small fw-semibold">Tipo de Turno</label>
                        <select class="form-select form-select-sm" id="type" name="type" required>
                            <option value="rotativo">Rotativo</option>
                            <option value="diurno">Diurno</option>
                            <option value="nocturno">Nocturno</option>
                            <option value="disponibilidad">Disponibilidad</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="starts_at" class="form-label small fw-semibold">Inicio del Turno</label>
                        <input type="datetime-local" class="form-control form-control-sm" id="starts_at" name="starts_at" required>
                    </div>

                    <div class="mb-3">
                        <label for="ends_at" class="form-label small fw-semibold">Fin del Turno</label>
                        <input type="datetime-local" class="form-control form-control-sm" id="ends_at" name="ends_at" required>
                    </div>

                    <button type="submit" class="btn btn-dark btn-sm w-100">
                        <i class="bi bi-calendar-check me-1" aria-hidden="true"></i>Programar Turno
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
