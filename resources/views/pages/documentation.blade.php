@extends('layouts.app')

@section('title', 'Documentación del Sistema y Seguridad - Hospital de Ubaté')

@section('content')
<div class="container py-2">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            {{-- Encabezado de Documentación --}}
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4 p-4 bg-white border rounded-3 shadow-sm">
                <div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill mb-2">
                        <i class="bi bi-shield-check me-1" aria-hidden="true"></i>Manual Técnico y Auditoría
                    </span>
                    <h1 class="h3 fw-bold text-dark mb-1">
                        <i class="bi bi-journal-text text-primary me-2" aria-hidden="true"></i>Documentación Oficial del Sistema
                    </h1>
                    <p class="small text-muted mb-0">Arquitectura asistencial, marco normativo colombiano y pruebas de seguridad peritadas.</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border font-monospace py-2 px-3 small">
                        <i class="bi bi-terminal me-1 text-primary" aria-hidden="true"></i>php artisan hospital:docs
                    </span>
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm py-2 px-3">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Inicio
                    </a>
                </div>
            </div>

            {{-- Contenedor del Informe Técnico Markdown Renderizado --}}
            <div class="card border-0 shadow-sm bg-white mb-4">
                <div class="card-body p-4 p-md-5 markdown-body">
                    {!! $htmlContent !!}
                </div>
            </div>

            {{-- Pie de Documentación --}}
            <div class="text-center py-3 text-muted small">
                Hospital de Ubaté &bull; Sistema de Gestión Hospitalaria y Urgencias &bull; Licencia Académica y Asistencial
            </div>
        </div>
    </div>
</div>
@endsection
