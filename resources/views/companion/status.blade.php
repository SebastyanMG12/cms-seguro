@extends('layouts.app')

@section('title', 'Estado del Paciente - Hospital El Salvador de Ubaté')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-md-9 col-lg-7">

        {{-- Barra Superior de Navegación y Salida Segura --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="badge bg-primary text-white px-2 py-1 small">
                    <i class="bi bi-shield-check me-1" aria-hidden="true"></i>Canal de Acompañante
                </span>
            </div>
            <form method="POST" action="{{ route('companion.logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm py-1 px-3" style="min-height: 38px;">
                    <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Cerrar Consulta
                </button>
            </form>
        </div>

        {{-- Tarjeta Principal del Paciente --}}
        <div class="card shadow-sm border-0 mb-3 bg-white">
            <div class="card-header bg-primary text-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="text-white-50 small d-block">Paciente en Urgencias</span>
                    <h1 class="h4 fw-bold mb-0 text-white">{{ $maskedName }}</h1>
                </div>
                <div class="text-end font-monospace tabular-nums">
                    <span class="text-white-50 small d-block">Código de Atención</span>
                    <strong class="fs-5 text-white">{{ $episode->public_code }}</strong>
                </div>
            </div>

            <div class="card-body p-4">

                {{-- Estado Actual Operativo --}}
                <div class="p-3 rounded mb-3 border @if ($episode->status === 'ingresado') bg-primary-subtle border-primary @elseif ($episode->status === 'en_valoracion') bg-warning-subtle border-warning @elseif ($episode->status === 'egresado') bg-success-subtle border-success @else bg-light border-secondary-subtle @endif">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            @if ($episode->status === 'preregistrado')
                                <i class="bi bi-clock-history fs-1 text-secondary" aria-hidden="true"></i>
                            @elseif ($episode->status === 'en_espera')
                                <i class="bi bi-hourglass-split fs-1 text-primary" aria-hidden="true"></i>
                            @elseif ($episode->status === 'en_valoracion')
                                <i class="bi bi-clipboard2-pulse fs-1 text-warning" aria-hidden="true"></i>
                            @elseif ($episode->status === 'ingresado')
                                <i class="bi bi-hospital fs-1 text-primary" aria-hidden="true"></i>
                            @elseif ($episode->status === 'egresado')
                                <i class="bi bi-check-circle fs-1 text-success" aria-hidden="true"></i>
                            @else
                                <i class="bi bi-info-circle fs-1 text-muted" aria-hidden="true"></i>
                            @endif
                        </div>
                        <div>
                            <span class="text-muted small d-block">Estado de Atención Actual:</span>
                            <div class="h5 fw-bold mb-0 text-dark">
                                @if ($episode->status === 'preregistrado')
                                    Pre-registro Digital (Pendiente de llegada física)
                                @elseif ($episode->status === 'en_espera')
                                    En Espera de Clasificación / Triaje
                                @elseif ($episode->status === 'en_valoracion')
                                    En Valoración Médica Inicial
                                @elseif ($episode->status === 'ingresado')
                                    Ingresado en el Servicio de Urgencias
                                @elseif ($episode->status === 'egresado')
                                    Atención de Urgencias Finalizada (Egresado)
                                @else
                                    {{ ucfirst(str_replace('_', ' ', $episode->status)) }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Ubicación y Equipo Asistencial (Sin nombres individuales) --}}
                <div class="row g-3 text-dark">
                    <div class="col-sm-6">
                        <div class="border rounded p-3 h-100 bg-light">
                            <span class="text-muted small d-block mb-1">
                                <i class="bi bi-geo-alt text-primary me-1" aria-hidden="true"></i>Ubicación Institucional:
                            </span>
                            <strong class="d-block">
                                @if ($episode->activeBedAssignment && $episode->activeBedAssignment->bed)
                                    Urgencias - Observación (Camilla {{ $episode->activeBedAssignment->bed->code }})
                                @elseif ($episode->status === 'ingresado')
                                    Urgencias - Sala de Observación
                                @elseif ($episode->status === 'en_valoracion')
                                    Urgencias - Consultorio de Valoración
                                @elseif ($episode->status === 'en_espera')
                                    Urgencias - Sala de Espera General
                                @elseif ($episode->status === 'egresado')
                                    Egresado de la Institución
                                @else
                                    Urgencias General
                                @endif
                            </strong>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="border rounded p-3 h-100 bg-light">
                            <span class="text-muted small d-block mb-1">
                                <i class="bi bi-people text-primary me-1" aria-hidden="true"></i>Equipo a Cargo:
                            </span>
                            <strong class="d-block">
                                Equipo Médico y de Enfermería del Servicio de Urgencias
                            </strong>
                        </div>
                    </div>
                </div>

                {{-- Aviso de Expiración Post-Egreso --}}
                @if ($episode->status === 'egresado')
                    <div class="alert alert-secondary border shadow-sm mt-3 mb-0 small" role="alert">
                        <i class="bi bi-calendar-event me-1" aria-hidden="true"></i>
                        <strong>Atención finalizada:</strong> El paciente ha egresado de urgencias. La consulta para este episodio permanecerá disponible hasta el <strong>{{ ($episode->closed_at ?? $episode->discharged_at)?->copy()->addHours(24)->format('d/m/Y \a \l\a\s H:i') }}</strong> (24 horas post-egreso).
                    </div>
                @endif

            </div>
        </div>

        {{-- Línea de Tiempo Vertical de Atención --}}
        <div class="card shadow-sm border-1 mb-3">
            <div class="card-header bg-white py-3 px-4">
                <h2 class="h5 mb-0 fw-semibold text-dark">
                    <i class="bi bi-clock-history me-2 text-primary" aria-hidden="true"></i>Línea de Tiempo del Paciente
                </h2>
            </div>
            <div class="card-body p-4">
                <div class="position-relative ps-4" style="border-left: 2px solid #cbd2d9; margin-left: 12px;">

                    {{-- 1. Llegada y Admisión --}}
                    <div class="position-relative mb-4">
                        <span class="position-absolute translate-middle-x rounded-circle d-flex align-items-center justify-content-center text-white @if ($episode->arrived_at) bg-success @else bg-secondary @endif" style="left: -25px; top: 0px; width: 26px; height: 26px;">
                            <i class="bi bi-check-lg small" aria-hidden="true"></i>
                        </span>
                        <div class="ms-2">
                            <div class="d-flex justify-content-between align-items-baseline flex-wrap">
                                <strong class="text-dark">1. Llegada y Recepción Física</strong>
                                <span class="font-monospace tabular-nums small text-muted">
                                    {{ $episode->arrived_at ? $episode->arrived_at->format('d/m/Y H:i') : 'Confirmada al ingreso' }}
                                </span>
                            </div>
                            <p class="text-muted small mb-0">Presentación física en el servicio de urgencias y recepción de documentos.</p>
                        </div>
                    </div>

                    {{-- 2. Clasificación de Triaje --}}
                    <div class="position-relative mb-4">
                        <span class="position-absolute translate-middle-x rounded-circle d-flex align-items-center justify-content-center text-white @if ($episode->triage_at) bg-success @elseif (in_array($episode->status, ['en_espera'])) bg-warning @else bg-secondary @endif" style="left: -25px; top: 0px; width: 26px; height: 26px;">
                            @if ($episode->triage_at)
                                <i class="bi bi-check-lg small" aria-hidden="true"></i>
                            @else
                                <i class="bi bi-dash small" aria-hidden="true"></i>
                            @endif
                        </span>
                        <div class="ms-2">
                            <div class="d-flex justify-content-between align-items-baseline flex-wrap">
                                <strong class="text-dark">2. Clasificación Médica (Triage)</strong>
                                <span class="font-monospace tabular-nums small text-muted">
                                    {{ $episode->triage_at ? $episode->triage_at->format('d/m/Y H:i') : 'En proceso o pendiente' }}
                                </span>
                            </div>
                            <p class="text-muted small mb-0">
                                @if ($episode->triage_at)
                                    Clasificación clínica completada por personal de salud.
                                @else
                                    Evaluación de signos vitales y prioridad asistencial.
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- 3. Ingreso y Atención en Urgencias --}}
                    <div class="position-relative mb-4">
                        <span class="position-absolute translate-middle-x rounded-circle d-flex align-items-center justify-content-center text-white @if ($episode->admitted_at) bg-success @elseif ($episode->status === 'en_valoracion') bg-warning @else bg-secondary @endif" style="left: -25px; top: 0px; width: 26px; height: 26px;">
                            @if ($episode->admitted_at)
                                <i class="bi bi-check-lg small" aria-hidden="true"></i>
                            @elseif ($episode->status === 'en_valoracion')
                                <i class="bi bi-arrow-repeat small" aria-hidden="true"></i>
                            @else
                                <i class="bi bi-dash small" aria-hidden="true"></i>
                            @endif
                        </span>
                        <div class="ms-2">
                            <div class="d-flex justify-content-between align-items-baseline flex-wrap">
                                <strong class="text-dark">3. Ingreso a Observación y Tratamiento</strong>
                                <span class="font-monospace tabular-nums small text-muted">
                                    {{ $episode->admitted_at ? $episode->admitted_at->format('d/m/Y H:i') : ($episode->status === 'en_valoracion' ? 'En valoración' : 'Pendiente') }}
                                </span>
                            </div>
                            <p class="text-muted small mb-0">Atención médica, monitorización continua y administración de procedimientos.</p>
                        </div>
                    </div>

                    {{-- 4. Egreso Hospitalario --}}
                    <div class="position-relative">
                        <span class="position-absolute translate-middle-x rounded-circle d-flex align-items-center justify-content-center text-white @if ($episode->status === 'egresado') bg-success @else bg-secondary @endif" style="left: -25px; top: 0px; width: 26px; height: 26px;">
                            @if ($episode->status === 'egresado')
                                <i class="bi bi-check-lg small" aria-hidden="true"></i>
                            @else
                                <i class="bi bi-dash small" aria-hidden="true"></i>
                            @endif
                        </span>
                        <div class="ms-2">
                            <div class="d-flex justify-content-between align-items-baseline flex-wrap">
                                <strong class="text-dark">4. Egreso o Remisión Médica</strong>
                                <span class="font-monospace tabular-nums small text-muted">
                                    {{ ($episode->closed_at ?? $episode->discharged_at) ? ($episode->closed_at ?? $episode->discharged_at)->format('d/m/Y H:i') : 'En curso' }}
                                </span>
                            </div>
                            <p class="text-muted small mb-0">
                                @if ($episode->status === 'egresado')
                                    Atención formalmente concluida en urgencias.
                                @else
                                    Pendiente según evolución médica del paciente.
                                @endif
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- Información Clínica Condicionada por Consentimiento (Ley 1581) --}}
        @if ($hasConsent)
            {{-- CASO 1: Paciente autorizó compartir información médica --}}
            <div class="card shadow-sm border-1 mb-3">
                <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2 class="h5 mb-0 fw-semibold text-dark">
                        <i class="bi bi-file-earmark-medical me-2 text-primary" aria-hidden="true"></i>Información Clínica Autorizada
                    </h2>
                    <span class="badge bg-success text-white small">
                        <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Autorizado por el paciente
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-light border small text-muted mb-3">
                        <i class="bi bi-info-circle text-primary me-1" aria-hidden="true"></i>
                        Esta información se comparte de forma exclusiva con fines informativos para el acompañante autorizado, en virtud del consentimiento otorgado el <strong>{{ $activeConsent?->granted_at?->format('d/m/Y \a \l\a\s H:i') }}</strong>.
                    </div>

                    @if ($episode->clinicalEntries->count() > 0)
                        <div class="list-group list-group-flush">
                            @foreach ($episode->clinicalEntries as $entry)
                                <div class="list-group-item px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-baseline mb-1">
                                        <span class="badge @if ($entry->type === 'diagnostico') bg-primary @else bg-secondary @endif text-white">
                                            {{ ucfirst($entry->type) }}
                                        </span>
                                        <span class="text-muted font-monospace tabular-nums small">
                                            {{ $entry->performed_at->format('d/m/Y H:i') }}
                                        </span>
                                    </div>
                                    <p class="mb-0 text-dark small" style="white-space: pre-line;">{{ $entry->content }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3 text-muted small">
                            <i class="bi bi-journal-text fs-3 d-block text-secondary mb-1" aria-hidden="true"></i>
                            Aún no se han registrado procedimientos o diagnósticos evolutivos en el sistema.
                        </div>
                    @endif
                </div>
            </div>
        @else
            {{-- CASO 2: Sin consentimiento o consentimiento revocado --}}
            <div class="card shadow-sm border-1 mb-3 bg-light">
                <div class="card-body p-4">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-shield-lock fs-2 text-primary me-3 mt-1" aria-hidden="true"></i>
                        <div>
                            <h2 class="h6 fw-bold text-dark mb-1">Protección de Datos Médicos (Ley 1581 de 2012)</h2>
                            <p class="small text-muted mb-2">
                                Por estricta protección de datos personales de salud (Ley Estatutaria 1581 de 2012 y Resolución 1995 de 1999), la información clínica diagnóstica y los procedimientos detallados <strong>únicamente se comparten con autorización expresa del paciente o su representante legal</strong>.
                            </p>
                            <p class="small text-muted mb-0">
                                Para conocer detalles sobre la condición de salud o evolución médica, consulte personalmente en el módulo de información médica presencial de urgencias en los horarios establecidos por el hospital.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Barra de Actualización y Refresco Manual --}}
        <div class="card shadow-sm border-0 mb-4 bg-white">
            <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small">
                    <i class="bi bi-arrow-repeat text-primary me-1" aria-hidden="true"></i>
                    Información actualizada en tiempo real
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm px-3" style="min-height: 40px;" onclick="window.location.reload();">
                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar Estado
                </button>
            </div>
        </div>

    </div>
</div>
@endsection
