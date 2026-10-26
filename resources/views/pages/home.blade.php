@extends('layouts.app')

@section('title', 'Hospital de Ubaté - Servicio de Urgencias y Atención Médica')

@section('content')
<div class="container py-2">
    {{-- Hero Institucional --}}
    <section class="p-4 p-md-5 mb-4 bg-white border rounded-3 shadow-sm" aria-labelledby="hero-title">
        <div class="row align-items-center gy-4">
            <div class="col-lg-8">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill mb-3">
                    <i class="bi bi-shield-check me-1" aria-hidden="true"></i>Atención de Urgencias 24/7 &bull; Provincia de Ubaté
                </span>
                <h1 id="hero-title" class="display-6 fw-bold text-dark mb-3">Hospital de Ubaté</h1>
                <p class="lead text-secondary mb-4">
                    Comprometidos con la atención médica oportuna, humanizada y clasificada bajo los más estrictos estándares de calidad clínica y seguridad del paciente.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-primary btn-lg px-4 py-2" href="{{ route('preregistro.create') }}">
                        <i class="bi bi-card-checklist me-2" aria-hidden="true"></i>Pre-registro de Urgencias
                    </a>
                    <a class="btn btn-outline-secondary btn-lg px-4 py-2" href="{{ route('about') }}">
                        <i class="bi bi-info-circle me-2" aria-hidden="true"></i>Conocer más
                    </a>
                    @guest
                        <a class="btn btn-outline-info btn-lg px-4 py-2" href="{{ route('companion.login') }}">
                            <i class="bi bi-people me-2" aria-hidden="true"></i>Módulo de Acompañantes
                        </a>
                    @endguest
                </div>
            </div>
            <div class="col-lg-4 text-center">
                <div class="p-4 bg-light rounded-3 border">
                    <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle p-3 mb-3 shadow-sm">
                        <i class="bi bi-heart-pulse fs-1" aria-hidden="true"></i>
                    </div>
                    <h2 class="h5 fw-bold text-dark mb-2">Servicio de Urgencias Activo</h2>
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Panel Clínico
                        </a>
                    @else
                        <a href="{{ route('contact.create') }}" class="btn btn-outline-dark btn-sm w-100">
                            <i class="bi bi-envelope me-1" aria-hidden="true"></i>Contáctenos
                        </a>
                    @endauth

                </div>
            </div>
        </div>
    </section>

    {{-- Clasificación de Triage (Resolución 5596 de 2015) --}}
    <section class="mb-5" aria-labelledby="triage-title">
        <div class="text-center mb-4">
            <h2 id="triage-title" class="h3 fw-bold text-dark">Clasificación de Triage de Urgencias</h2>
            <p class="text-secondary max-w-75 mx-auto">
                En cumplimiento con la <strong>Resolución 5596 de 2015 del Ministerio de Salud</strong>, el orden de atención se determina exclusivamente por la gravedad clínica del paciente, no por el orden de llegada.
            </p>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6 col-lg">
                <div class="card h-100 border-danger shadow-sm">
                    <div class="card-header bg-danger text-white text-center py-2 fw-bold">
                        Triage I &bull; Reanimación
                    </div>
                    <div class="card-body">
                        <h3 class="h6 fw-bold text-danger">Atención Inmediata</h3>
                        <p class="small text-muted mb-2">Pacientes en riesgo vital inminente con paro cardiorrespiratorio, pérdida súbita de conciencia o shock.</p>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Tiempo: 0 minutos</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg">
                <div class="card h-100 border-warning shadow-sm">
                    <div class="card-header bg-warning text-dark text-center py-2 fw-bold">
                        Triage II &bull; Emergencia
                    </div>
                    <div class="card-body">
                        <h3 class="h6 fw-bold text-warning-emphasis">Riesgo Alto</h3>
                        <p class="small text-muted mb-2">Dolor torácico severo, dificultad respiratoria aguda o déficit neurológico agudo con riesgo de deterioro.</p>
                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle">Hasta 30 minutos</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg">
                <div class="card h-100 border-primary shadow-sm">
                    <div class="card-header bg-primary text-white text-center py-2 fw-bold">
                        Triage III &bull; Urgencia
                    </div>
                    <div class="card-body">
                        <h3 class="h6 fw-bold text-primary">Condición Estable</h3>
                        <p class="small text-muted mb-2">Pacientes que requieren exámenes diagnósticos, observación o tratamiento endovenoso en urgencias.</p>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Hasta 120 minutos</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg">
                <div class="card h-100 border-success shadow-sm">
                    <div class="card-header bg-success text-white text-center py-2 fw-bold">
                        Triage IV &bull; Prioritaria
                    </div>
                    <div class="card-body">
                        <h3 class="h6 fw-bold text-success">Urgencia Menor</h3>
                        <p class="small text-muted mb-2">Condiciones médicas sin riesgo vital ni funcional que pueden ser derivadas a consulta prioritaria.</p>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Consulta prioritaria</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg">
                <div class="card h-100 border-secondary shadow-sm">
                    <div class="card-header bg-secondary text-white text-center py-2 fw-bold">
                        Triage V &bull; No Urgente
                    </div>
                    <div class="card-body">
                        <h3 class="h6 fw-bold text-secondary">Condición Crónica</h3>
                        <p class="small text-muted mb-2">Problemas médicos sin deterioro agudo que deben ser valorados por consulta externa ambulatoria.</p>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Consulta externa</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Signos de Alarma y Servicios Asistenciales --}}
    <div class="row g-4 mb-5">
        <div class="col-12 col-lg-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-3 me-2" aria-hidden="true"></i>
                        <h2 class="h5 fw-bold text-dark mb-0">¿Cuándo acudir de inmediato a Urgencias?</h2>
                    </div>
                    <p class="text-secondary small mb-3">Si usted o un familiar presenta alguno de los siguientes signos, diríjase inmediatamente a nuestro servicio:</p>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex align-items-start px-0">
                            <i class="bi bi-check-circle-fill text-danger me-2 mt-1" aria-hidden="true"></i>
                            <div><strong>Dolor torácico opresivo</strong> súbito, irradiado a brazo o cuello, acompañado de sudoración fría.</div>
                        </li>
                        <li class="list-group-item d-flex align-items-start px-0">
                            <i class="bi bi-check-circle-fill text-danger me-2 mt-1" aria-hidden="true"></i>
                            <div><strong>Dificultad respiratoria severa</strong>, sensación de asfixia o labios morados (cianosis).</div>
                        </li>
                        <li class="list-group-item d-flex align-items-start px-0">
                            <i class="bi bi-check-circle-fill text-danger me-2 mt-1" aria-hidden="true"></i>
                            <div><strong>Pérdida súbita del habla</strong>, desviación de la boca o debilidad en un lado del cuerpo.</div>
                        </li>
                        <li class="list-group-item d-flex align-items-start px-0">
                            <i class="bi bi-check-circle-fill text-danger me-2 mt-1" aria-hidden="true"></i>
                            <div><strong>Convulsiones</strong>, desmayos prolongados o pérdida súbita del conocimiento.</div>
                        </li>
                        <li class="list-group-item d-flex align-items-start px-0">
                            <i class="bi bi-check-circle-fill text-danger me-2 mt-1" aria-hidden="true"></i>
                            <div><strong>Fiebre alta persistente</strong> en menores de 3 meses o adultos mayores con desorientación.</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-building-check text-primary fs-3 me-2" aria-hidden="true"></i>
                        <h2 class="h5 fw-bold text-dark mb-0">Capacidad Asistencial</h2>
                    </div>
                    <p class="text-secondary small mb-3">El Hospital de Ubaté cuenta con infraestructura médica para la resolución oportuna de episodios de urgencia:</p>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="fw-bold text-dark mb-1"><i class="bi bi-hospital me-1 text-primary"></i>Sala de Reanimación</div>
                                <p class="small text-muted mb-0">Equipamiento con desfibrilador, monitor multiparámetros y ventilación de emergencia.</p>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="fw-bold text-dark mb-1"><i class="bi bi-bed me-1 text-primary"></i>Observación Clínica</div>
                                <p class="small text-muted mb-0">Monitoreo médico continuo de adultos y pediátricos con gestión de camas en tiempo real.</p>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="fw-bold text-dark mb-1"><i class="bi bi-clipboard2-pulse me-1 text-primary"></i>Apoyo Diagnóstico</div>
                                <p class="small text-muted mb-0">Laboratorio clínico automatizado y radiología básica de urgencias.</p>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <div class="fw-bold text-dark mb-1"><i class="bi bi-shield-lock me-1 text-primary"></i>Historia Clínica Segura</div>
                                <p class="small text-muted mb-0">Custodia electrónica bajo Resolución 1995 de 1999 y Ley de Datos 1581 de 2012.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Información de Contacto y Ubicación --}}
    <section class="bg-white border rounded-3 p-4 shadow-sm" aria-labelledby="contact-section-title">
        <div class="row align-items-center gy-3">
            <div class="col-12 col-md-8">
                <h2 id="contact-section-title" class="h5 fw-bold text-dark mb-1">
                    <i class="bi bi-geo-alt-fill text-danger me-1" aria-hidden="true"></i>Atención al Usuario y Localización
                </h2>
                <p class="text-secondary small mb-0">
                    Sede principal en el municipio de Ubaté, Cundinamarca. Servicio de Urgencias habilitado las 24 horas del día, los 365 días del año.
                </p>
            </div>
            <div class="col-12 col-md-4 text-md-end">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary px-3 py-2">
                        <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Ir al Panel Principal
                    </a>
                @else
                    <a href="{{ route('contact.create') }}" class="btn btn-outline-primary px-3 py-2">
                        <i class="bi bi-envelope me-1" aria-hidden="true"></i>Radicar Mensaje / Consulta
                    </a>
                @endauth
            </div>

        </div>
    </section>
</div>
@endsection