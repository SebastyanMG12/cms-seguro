@extends('layouts.app')

@section('title', 'Consulta de Acompañante - Hospital El Salvador de Ubaté')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5">

        {{-- Encabezado Institucional --}}
        <div class="card shadow-sm border-0 mb-4 bg-primary text-white">
            <div class="card-body p-4 text-center">
                <div class="mb-2">
                    <i class="bi bi-hospital fs-1" aria-hidden="true"></i>
                </div>
                <h1 class="h4 fw-bold mb-1">Hospital El Salvador de Ubaté E.S.E.</h1>
                <p class="mb-0 fs-6">Seguimiento de Urgencias para Acompañantes</p>
                <small class="opacity-75">Canal oficial de información en tiempo real</small>
            </div>
        </div>

        {{-- Aviso de Entrega Presencial de PIN (Ley 1581 de 2012) --}}
        <div class="alert alert-info border-info shadow-sm mb-3" role="alert">
            <div class="d-flex align-items-start">
                <i class="bi bi-shield-lock fs-4 text-primary me-2 mt-1" aria-hidden="true"></i>
                <div class="small">
                    <strong>Acceso Protegido:</strong> Por estrictas razones de confidencialidad médica y custodia de la historia clínica (Ley 1581 de 2012 y Res. 1995 de 1999), el PIN numérico es entregado <strong>únicamente de manera presencial</strong> al acompañante autorizado por el personal de admisiones o enfermería.
                </div>
            </div>
        </div>

        {{-- Errores de Validación o Autenticación Anti-Enumeración --}}
        @if ($errors->has('auth'))
            <div class="alert alert-danger border-danger shadow-sm mb-3" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-danger me-2" aria-hidden="true"></i>
                    <div>
                        <strong class="d-block">No fue posible acceder:</strong>
                        <span class="small">{{ $errors->first('auth') }}</span>
                    </div>
                </div>
            </div>
        @endif

        @if (session('info'))
            <div class="alert alert-secondary border shadow-sm mb-3 small" role="alert">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i> {{ session('info') }}
            </div>
        @endif

        {{-- Tarjeta de Acceso --}}
        <div class="card shadow-sm border-1">
            <div class="card-header bg-white py-3 px-4">
                <h2 class="h5 mb-0 fw-semibold text-dark">
                    <i class="bi bi-key me-2 text-primary" aria-hidden="true"></i>Ingrese sus Credenciales de Acceso
                </h2>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('companion.auth') }}" novalidate>
                    @csrf

                    {{-- Código del Paciente --}}
                    <div class="mb-3">
                        <label for="public_code" class="form-label fw-semibold text-dark">
                            Código del Paciente <span class="text-danger">*</span>
                        </label>
                        <input
                            type="text"
                            class="form-control form-control-lg font-monospace text-uppercase tabular-nums @error('public_code') is-invalid @enderror"
                            id="public_code"
                            name="public_code"
                            value="{{ old('public_code') }}"
                            placeholder="Ej: UBT1A2B3"
                            maxlength="12"
                            required
                            autocomplete="off"
                            autofocus
                            style="min-height: 48px; letter-spacing: 2px;"
                        >
                        @error('public_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text small">
                                Código alfanumérico entregado al registrarse en el servicio de urgencias.
                            </div>
                        @enderror
                    </div>

                    {{-- PIN de Acceso --}}
                    <div class="mb-4">
                        <label for="pin" class="form-label fw-semibold text-dark">
                            PIN de Acceso <span class="text-danger">*</span>
                        </label>
                        <input
                            type="password"
                            class="form-control form-control-lg font-monospace tabular-nums @error('pin') is-invalid @enderror"
                            id="pin"
                            name="pin"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            placeholder="••••••"
                            required
                            autocomplete="off"
                            style="min-height: 48px; letter-spacing: 4px;"
                        >
                        @error('pin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text small">
                                PIN de 6 dígitos entregado presencialmente por el personal hospitalario.
                            </div>
                        @enderror
                    </div>

                    {{-- Botón de Consulta --}}
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold" style="min-height: 48px;">
                            <i class="bi bi-search me-2" aria-hidden="true"></i>Consultar Estado del Paciente
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light p-3 text-center small text-muted">
                <i class="bi bi-shield-check text-success me-1" aria-hidden="true"></i>
                Tratamiento seguro de datos de salud conforme a la Ley 1581 de 2012.
            </div>
        </div>

        {{-- Enlace a Pre-registro público --}}
        <div class="text-center mt-4">
            <span class="text-muted small">¿El paciente aún no ha sido registrado?</span>
            <a href="{{ route('preregistro.create') }}" class="small fw-semibold text-decoration-none ms-1">
                Realizar Pre-registro Digital
            </a>
        </div>

    </div>
</div>
@endsection
