@extends('layouts.app')
@section('title', 'Editar usuario')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Editar usuario: {{ $user->name }}</h1>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Volver al listado</a>
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

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">Datos del usuario</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre</label>
                        <input id="name" name="name" value="{{ old('name', $user->name) }}"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}"
                               class="form-control @error('email') is-invalid @enderror" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Roles asignados</label>
                        @if (auth()->id() === $user->id)
                            <div class="alert alert-warning py-2 small mb-2">
                                Por política de seguridad, no puede modificar sus propios roles.
                            </div>
                        @endif
                        @foreach ($roles as $role)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="roles[]" id="role_{{ $role }}"
                                       value="{{ $role }}"
                                       @checked(in_array($role, old('roles', $userRoles)))
                                       @disabled(auth()->id() === $user->id)>
                                <label class="form-check-label" for="role_{{ $role }}">{{ $role }}</label>
                            </div>
                        @endforeach
                        @if (auth()->id() === $user->id)
                            @foreach ($userRoles as $r)
                                <input type="hidden" name="roles[]" value="{{ $r }}">
                            @endforeach
                        @endif
                        @error('roles') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h5 mb-0">Estado y Seguridad</h2>
            </div>
            <div class="card-body">
                <p class="mb-2"><strong>Estado actual:</strong>
                    @if ($user->status === 'active')
                        <span class="badge bg-success">Activo</span>
                    @else
                        <span class="badge bg-danger">Suspendido</span>
                    @endif
                </p>
                <p class="mb-3"><strong>Doble factor (2FA):</strong>
                    @if ($user->hasTwoFactorEnabled())
                        <span class="badge bg-primary">Activado</span>
                    @else
                        <span class="badge bg-secondary">No configurado</span>
                    @endif
                </p>

                <hr>

                {{-- Suspender / Reactivar --}}
                @if ($user->status === 'active')
                    @if (auth()->id() !== $user->id)
                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="mb-3">
                            @csrf
                            <label for="suspend_reason" class="form-label small fw-bold">Motivo de suspensión</label>
                            <textarea id="suspend_reason" name="reason" rows="2" class="form-control form-control-sm mb-2" required placeholder="Indique el motivo..."></textarea>
                            <button type="submit" class="btn btn-outline-warning btn-sm w-100" onclick="return confirm('¿Seguro que desea suspender este usuario?')">Suspender usuario</button>
                        </form>
                    @else
                        <p class="text-muted small">No puede suspender su propia cuenta.</p>
                    @endif
                @else
                    <form method="POST" action="{{ route('admin.users.reactivate', $user) }}" class="mb-3">
                        @csrf
                        <label for="reactivate_reason" class="form-label small fw-bold">Motivo de reactivación</label>
                        <textarea id="reactivate_reason" name="reason" rows="2" class="form-control form-control-sm mb-2" required placeholder="Indique el motivo..."></textarea>
                        <button type="submit" class="btn btn-outline-success btn-sm w-100">Reactivar usuario</button>
                    </form>
                @endif

                {{-- Restablecer 2FA --}}
                @if ($user->hasTwoFactorEnabled())
                    <hr>
                    <form method="POST" action="{{ route('admin.users.reset-2fa', $user) }}" class="mb-3">
                        @csrf
                        <label for="reset_2fa_reason" class="form-label small fw-bold">Restablecer 2FA (motivo obligatorio)</label>
                        <textarea id="reset_2fa_reason" name="reason" rows="2" class="form-control form-control-sm mb-2" required placeholder="Ej: Pérdida de dispositivo confirmada..."></textarea>
                        <button type="submit" class="btn btn-outline-secondary btn-sm w-100" onclick="return confirm('¿Restablecer el segundo factor para este usuario?')">Restablecer 2FA</button>
                    </form>
                @endif

                {{-- Restablecer Contraseña por Administrador --}}
                <hr>
                <form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="mb-3">
                    @csrf
                    <div class="fw-bold small text-dark mb-1">
                        <i class="bi bi-key me-1 text-primary" aria-hidden="true"></i>Restablecer Contraseña
                    </div>
                    <div class="mb-2">
                        <label for="new_password" class="form-label small text-muted mb-0">Nueva Contraseña</label>
                        <input type="password" id="new_password" name="password" class="form-control form-control-sm" required placeholder="Mínimo 8 caracteres">
                    </div>
                    <div class="mb-2">
                        <label for="new_password_confirmation" class="form-label small text-muted mb-0">Confirmar Contraseña</label>
                        <input type="password" id="new_password_confirmation" name="password_confirmation" class="form-control form-control-sm" required placeholder="Repetir contraseña">
                    </div>
                    <div class="mb-2">
                        <label for="pwd_reset_reason" class="form-label small text-muted mb-0">Motivo (auditoría)</label>
                        <input type="text" id="pwd_reset_reason" name="reason" class="form-control form-control-sm" required placeholder="Ej: Olvido sin correo / restablecimiento administrativo">
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100" onclick="return confirm('¿Restablecer la contraseña de este usuario? Se cerrarán todas sus sesiones activas.')">
                        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>Cambiar Contraseña
                    </button>
                </form>

                {{-- Borrado Lógico --}}
                @if (auth()->id() !== $user->id)
                    <hr>
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('¿Está seguro de eliminar este usuario? (borrado lógico)')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">Eliminar usuario</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
