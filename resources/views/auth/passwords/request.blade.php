@extends('layouts.app')
@section('title', 'Recuperar contraseña')
@section('content')
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
<div class="card shadow-sm"><div class="card-body p-4">
    <h1 class="h3 mb-3">Recuperar contraseña</h1>
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror" required>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <button class="btn btn-primary w-100">Enviar código</button>
    </form>
</div></div></div></div>
@endsection