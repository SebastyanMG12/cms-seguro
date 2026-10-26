@extends('layouts.app')

@section('title', 'Editar Profesional Clínico - ' . $profile->user->name)

@section('content')
<div class="container-fluid px-0">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2 small">
            <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="text-decoration-none">Administración</a></li>
            <li class="breadcrumb-item"><a href="{{ route('staff.index') }}" class="text-decoration-none">Personal y Turnos</a></li>
            <li class="breadcrumb-item active" aria-current="page">Editar: {{ $profile->user->name }}</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0 fw-bold text-dark">
                <i class="bi bi-person-gear text-primary me-2" aria-hidden="true"></i>Editar Profesional Clínico
            </h1>
            <p class="text-muted small mb-0">Modificación de información asistencial, especialidad y estado de servicio.</p>
        </div>
        <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a la plantilla
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2 px-3 small" role="alert">
            <div class="fw-bold mb-1 d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>No se pudieron guardar los cambios:
            </div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm border mb-3">
                <div class="card-header bg-white py-2 px-3">
                    <h2 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-card-checklist me-2 text-primary" aria-hidden="true"></i>Información del Profesional
                    </h2>
                </div>
                <div class="card-body p-3">
                    <form method="POST" action="{{ route('staff.update', $profile) }}">
                        @csrf
                        @method('PUT')

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-muted">Código Asistencial Único</label>
                                <input type="text" class="form-control form-control-sm font-monospace tabular-nums bg-light" value="{{ $profile->code }}" readonly>
                                <div class="form-text small">Identificador institucional único generado automáticamente.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label small fw-semibold">Estado de Disponibilidad <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="status" name="status" required>
                                    <option value="activo" {{ old('status', $profile->status) === 'activo' ? 'selected' : '' }}>
                                        Activo (Disponible para turnos y asignaciones)
                                    </option>
                                    <option value="en_descanso" {{ old('status', $profile->status) === 'en_descanso' ? 'selected' : '' }}>
                                        En descanso (Pausa temporal, no asignable a nuevos episodios)
                                    </option>
                                    <option value="inactivo" {{ old('status', $profile->status) === 'inactivo' ? 'selected' : '' }}>
                                        Inactivo (Fuera de servicio)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label small fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="name" name="name" value="{{ old('name', $profile->user->name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label small fw-semibold">Correo Institucional <span class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-sm" id="email" name="email" value="{{ old('email', $profile->user->email) }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="specialty" class="form-label small fw-semibold">Especialidad / Cargo Asistencial <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="specialty" name="specialty" value="{{ old('specialty', $profile->specialty) }}" required placeholder="Ej: Médico Urgenciólogo, Pediatra, Enfermera Jefe">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold d-block">Roles Asistenciales Asignados <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-3">
                                @php
                                    $userRoleNames = $profile->user->roles->pluck('name')->all();
                                @endphp
                                @foreach ($availableRoles as $role)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="roles[]" id="role_{{ $role }}" value="{{ $role }}"
                                            {{ in_array($role, old('roles', $userRoleNames), true) ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="role_{{ $role }}">
                                            <span class="badge bg-primary-subtle text-primary border">{{ ucfirst($role) }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text small">Debe contar con al menos un rol asistencial válido en el sistema.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary btn-sm px-3 py-2">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm px-4 py-2">
                                <i class="bi bi-check2 me-1" aria-hidden="true"></i>Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Panel Lateral Informativo --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border mb-3">
                <div class="card-header bg-white py-2 px-3">
                    <h2 class="h6 mb-0 fw-bold text-dark">
                        <i class="bi bi-activity me-2 text-primary" aria-hidden="true"></i>Carga Asistencial Actual
                    </h2>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <span class="small text-muted">Pacientes activos:</span>
                        <span class="badge bg-info-subtle text-dark border fs-6 tabular-nums">
                            {{ $activePatientsCount }} caso(s)
                        </span>
                    </div>

                    <div class="small text-muted mb-2">
                        <i class="bi bi-shield-lock me-1 text-secondary" aria-hidden="true"></i>
                        <strong>Seguridad Clínica:</strong> Si el profesional se encuentra en descanso o inactivo, no podrá ser seleccionado como médico responsable ni personal de apoyo en nuevos episodios de urgencias.
                    </div>

                    @if ($activePatientsCount > 0)
                        <div class="alert alert-warning py-2 px-3 small mb-0 mt-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>
                            Este profesional tiene <strong>{{ $activePatientsCount }}</strong> paciente(s) activos. Si finaliza su turno, los pacientes deberán ser reasignados previamente.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
