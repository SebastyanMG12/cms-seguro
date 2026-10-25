@extends('layouts.app')

@section('title', 'Gestión de Personal y Turnos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Personal Asistencial y Turnos</h1>
        <p class="text-muted mb-0">Administración de perfiles clínicos, disponibilidad y programación de turnos.</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    {{-- Listado de Personal --}}
    <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0 fw-bold">Plantilla de Profesionales</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
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
                                <td><span class="badge bg-secondary font-monospace">{{ $p->code }}</span></td>
                                <td>
                                    <strong>{{ $p->user->name }}</strong><br>
                                    <small class="text-muted">{{ $p->user->email }}</small>
                                </td>
                                <td>
                                    <div>
                                        @foreach ($p->user->roles as $role)
                                            <span class="badge bg-primary-subtle text-primary border">{{ $role->name }}</span>
                                        @endforeach
                                    </div>
                                    <small class="text-muted">{{ $p->specialty }}</small>
                                </td>
                                <td>
                                    @switch($p->status)
                                        @case('activo')
                                            <span class="badge bg-success">Activo</span>
                                            @break
                                        @case('en_descanso')
                                            <span class="badge bg-warning text-dark">En descanso</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">Inactivo</span>
                                    @endswitch
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if ($p->status === 'activo')
                                            <form method="POST" action="{{ route('staff.update-status', $p) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="en_descanso">
                                                <button type="submit" class="btn btn-outline-warning btn-sm" title="Poner en descanso">Descanso</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('staff.update-status', $p) }}">
                                                @csrf
                                                <input type="hidden" name="status" value="activo">
                                                <button type="submit" class="btn btn-outline-success btn-sm" title="Reactivar disponibilidad">Activar</button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('staff.destroy', $p) }}" onsubmit="return confirm('¿Desactivar este perfil?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Desactivar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay perfiles de personal registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($staff->hasPages())
                <div class="card-footer bg-white">
                    {{ $staff->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>

    {{-- Formularios Laterales --}}
    <div class="col-lg-4">
        {{-- Registrar Perfil --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h3 class="h6 mb-0 fw-bold">Habilitar Perfil Clínico</h3>
            </div>
            <div class="card-body">
                @if ($eligibleUsers->isNotEmpty())
                    <form method="POST" action="{{ route('staff.store') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Usuario</label>
                            <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                                <option value="">Seleccione un usuario...</option>
                                @foreach ($eligibleUsers as $eu)
                                    <option value="{{ $eu->id }}">{{ $eu->name }} ({{ $eu->roles->pluck('name')->join(', ') }})</option>
                                @endforeach
                            </select>
                            @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label for="specialty" class="form-label">Especialidad</label>
                            <input id="specialty" name="specialty" class="form-control @error('specialty') is-invalid @enderror"
                                   placeholder="Ej: Médico General, Urgencias, Triage" required>
                            @error('specialty') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Crear perfil asistencial</button>
                    </form>
                @else
                    <p class="text-muted small mb-0">Todos los usuarios clínicos actuales ya disponen de un perfil asistencial activo.</p>
                @endif
            </div>
        </div>

        {{-- Programar Turno --}}
        @can('turnos.gestionar')
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h3 class="h6 mb-0 fw-bold">Programar Turno</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('shifts.store') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="shift_user_id" class="form-label">Profesional</label>
                            <select id="shift_user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                                <option value="">Seleccione...</option>
                                @foreach ($staff as $sp)
                                    <option value="{{ $sp->user->id }}">{{ $sp->user->name }} ({{ $sp->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="starts_at" class="form-label">Inicio</label>
                            <input id="starts_at" type="datetime-local" name="starts_at" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="ends_at" class="form-label">Fin</label>
                            <input id="ends_at" type="datetime-local" name="ends_at" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="type" class="form-label">Tipo de Turno</label>
                            <select id="type" name="type" class="form-select">
                                <option value="diurno">Diurno (07:00 - 19:00)</option>
                                <option value="nocturno">Nocturno (19:00 - 07:00)</option>
                                <option value="guardia">Guardia 24h</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-outline-primary w-100">Programar turno</button>
                    </form>
                </div>
            </div>
        @endcan
    </div>
</div>
@endsection
