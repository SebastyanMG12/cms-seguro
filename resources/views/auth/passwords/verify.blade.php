@extends('layouts.app')
@section('title', 'Ingresar código')
@section('content')
<div class="row justify-content-center"><div class="col-md-6 col-lg-5">
<div class="card shadow-sm"><div class="card-body p-4">
    <h1 class="h3 mb-3">Ingrese el código</h1>
    <form method="POST" action="{{ route('password.verify.store') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label for="code" class="form-label">Código de 6 dígitos</label>
            <input id="code" type="text" name="code" inputmode="numeric" maxlength="6"
                   autocomplete="one-time-code"
                   class="form-control @error('code') is-invalid @enderror" required>
            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <button class="btn btn-primary w-100">Verificar</button>
    </form>
    <p class="mt-3 mb-0 text-center"><a href="{{ route('password.request') }}">Reenviar código</a></p>
</div></div></div></div>
@endsection