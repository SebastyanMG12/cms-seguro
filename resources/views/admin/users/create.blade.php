@extends('layouts.app')
@section('title', 'Nuevo usuario')
@section('content')
<h1 class="h3 mb-3">Nuevo usuario</h1>
<form method="POST" action="{{ route('admin.users.store') }}" novalidate>
    @csrf
    <div class="mb-3">
        <label for="name" class="form-label">Nombre</label>
        <input id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label for="email" class="form-label">Correo</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label for="role" class="form-label">Rol</label>
        <div class="mb-3">
    <label class="form-label">Roles</label>
    @foreach ($roles as $role)
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="roles[]" id="role_{{ $role }}"
                   value="{{ $role }}" @checked(in_array($role, old('roles', [])))>
            <label class="form-check-label" for="role_{{ $role }}">{{ $role }}</label>
        </div>
    @endforeach
    @error('roles') <div class="text-danger small">{{ $message }}</div> @enderror
</div>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <input id="password" type="password" name="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" required>
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="mb-3">
        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" class="form-control" required>
    </div>
    <button class="btn btn-primary">Crear usuario</button>
    <a href="{{ route('admin.users.index') }}" class="btn btn-link">Cancelar</a>
</form>
@endsection