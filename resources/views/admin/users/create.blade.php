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
        <select id="role" name="role" class="form-select">
            <option value="author" @selected(old('role') === 'author')>Autor</option>
            <option value="editor" @selected(old('role') === 'editor')>Editor</option>
            <option value="admin" @selected(old('role') === 'admin')>Administrador</option>
        </select>
        @error('role') <div class="text-danger small">{{ $message }}</div> @enderror
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