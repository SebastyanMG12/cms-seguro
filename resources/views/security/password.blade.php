@extends('layouts.app')

@section('title', 'Cambiar contraseña')

@section('content')
<div class="row">
    <div class="col-md-3 mb-4">
        @include('partials.sidebar')
    </div>
    <div class="col-md-9">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Cambiar contraseña</h1>
                <p class="text-muted">Por seguridad, al cambiar la contraseña se cerrarán todas las demás sesiones activas.</p>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('security.password.update') }}" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Contraseña actual</label>
                        <input id="current_password" type="password" name="current_password"
                               class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva contraseña</label>
                        <input id="password" type="password" name="password" autocomplete="new-password"
                               class="form-control @error('password') is-invalid @enderror" required>
                        <div class="form-text">Mínimo 12 caracteres, incluyendo mayúsculas, minúsculas, números y símbolos.</div>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmar nueva contraseña</label>
                        <input id="password_confirmation" type="password" name="password_confirmation"
                               autocomplete="new-password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Actualizar contraseña</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-link">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
