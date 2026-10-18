@extends('layouts.app')
@section('title', 'Nueva contraseña')
@section('content')
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
<div class="card shadow-sm"><div class="card-body p-4">
    <h1 class="h3 mb-3">Nueva contraseña</h1>
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <input id="password" type="password" name="password" autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror" required>
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">Mínimo 12 caracteres, con mayúsculas, minúsculas, números y símbolos.</div>
        </div>
        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                   autocomplete="new-password" class="form-control" required>
        </div>
        <button class="btn btn-primary w-100">Actualizar contraseña</button>
    </form>
</div></div></div></div>
@endsection