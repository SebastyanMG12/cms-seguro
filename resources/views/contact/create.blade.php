@extends('layouts.app')

@section('title', 'Contacto y Atención al Usuario - Hospital de Ubaté')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill mb-1">
                        <i class="bi bi-envelope me-1" aria-hidden="true"></i>Canal Institucional
                    </span>
                    <h1 class="h4 mb-0 fw-bold text-dark">Contacto y Atención al Usuario</h1>
                    <p class="small text-muted mb-0">Radique sus solicitudes, dudas o consultas dirigidas a la administración del Hospital de Ubaté.</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('contact.store') }}" novalidate>
                        @csrf
                        <div style="position:absolute;left:-9999px;" aria-hidden="true">
                            <label for="website">No rellenar este campo</label>
                            <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold text-dark">Nombre completo <span class="text-danger">*</span></label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}"
                                class="form-control @error('name') is-invalid @enderror" maxlength="100" placeholder="Ingrese su nombre y apellido" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold text-dark">Correo electrónico <span class="text-danger">*</span></label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror" maxlength="255" placeholder="ejemplo@correo.com" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label fw-semibold text-dark">Mensaje o solicitud <span class="text-danger">*</span></label>
                            <textarea id="message" name="message" rows="5"
                                class="form-control @error('message') is-invalid @enderror" maxlength="5000"
                                placeholder="Escriba aquí los detalles de su consulta..." required>{{ old('message') }}</textarea>
                            @error('message')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-send me-1" aria-hidden="true"></i>Enviar mensaje
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection