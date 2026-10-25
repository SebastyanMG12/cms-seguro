@extends('layouts.app')

@section('title', 'Verificación en dos pasos')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <h1 class="h3 mb-3">Verificación en dos pasos</h1>

        @if ($recoveryCodes)
            <div class="alert alert-warning">
                <strong>Guarde estos códigos de recuperación ahora.</strong>
                No se volverán a mostrar. Cada uno sirve una sola vez si pierde su teléfono.
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <ul class="list-unstyled mb-0 font-monospace">
                        @foreach ($recoveryCodes as $code)
                            <li>{{ $code }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if ($enabled)
            <div class="alert alert-success mb-0">La verificación en dos pasos está activa en su cuenta.</div>
        @else
            <div class="card shadow-sm">
                <div class="card-body">
                    <ol>
                        <li>Instale una aplicación de autenticación (Google Authenticator, Microsoft Authenticator o similar).</li>
                        <li>Escanee este código QR.</li>
                        <li>Escriba el código de 6 dígitos que muestra la aplicación.</li>
                    </ol>

                    <div class="text-center my-3">{!! $qr !!}</div>

                    <p class="small text-muted">
                        Si no puede escanear, escriba esta clave manualmente:
                        <span class="font-monospace">{{ $secret }}</span>
                    </p>

                    <form method="POST" action="{{ route('two-factor.confirm') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="code" class="form-label">Código de 6 dígitos</label>
                            <input id="code" name="code" inputmode="numeric" maxlength="6"
                                   autocomplete="one-time-code"
                                   class="form-control @error('code') is-invalid @enderror" required>
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <button class="btn btn-primary">Activar</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection