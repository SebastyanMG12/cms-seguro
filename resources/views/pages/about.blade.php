@extends('layouts.app')

@section('title', 'Acerca del Hospital de Ubaté')

@section('content')
<div class="container py-2">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-10">
            {{-- Encabezado --}}
            <div class="bg-white border rounded-3 p-4 p-md-5 mb-4 shadow-sm">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill mb-2">
                    <i class="bi bi-hospital me-1" aria-hidden="true"></i>Compromiso Asistencial y Ético
                </span>
                <h1 class="display-6 fw-bold text-dark mb-3">Acerca del Hospital de Ubaté</h1>
                <p class="lead text-secondary mb-0">
                    Institución prestadora de servicios de salud enfocada en brindar atención de urgencias oportuna, segura y con profundo sentido humano a la comunidad de Ubaté y la región circunvecina.
                </p>
            </div>

            {{-- Misión y Visión --}}
            <div class="row g-4 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary-subtle text-primary rounded-circle p-2 me-3">
                                    <i class="bi bi-compass fs-4" aria-hidden="true"></i>
                                </div>
                                <h2 class="h5 fw-bold text-dark mb-0">Misión Institucional</h2>
                            </div>
                            <p class="text-secondary small mb-0">
                                Prestar servicios de salud de mediana y baja complejidad con altos estándares de calidad, garantizando la seguridad del paciente, la pertinencia médica y el acceso equitativo a toda la población del valle de Ubaté.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary-subtle text-primary rounded-circle p-2 me-3">
                                    <i class="bi bi-eye fs-4" aria-hidden="true"></i>
                                </div>
                                <h2 class="h5 fw-bold text-dark mb-0">Visión</h2>
                            </div>
                            <p class="text-secondary small mb-0">
                                Consolidarnos como centro hospitalario de referencia en la provincia de Ubaté, reconocido por la excelencia en la atención de urgencias, la innovación en la gestión asistencial y el respeto irrestricto a los derechos de los usuarios.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Marco Normativo y Cumplimiento Legal --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold text-dark mb-3">
                        <i class="bi bi-journal-bookmark text-primary me-2" aria-hidden="true"></i>Marco Normativo y Seguridad Jurídica
                    </h2>
                    <p class="text-secondary small mb-3">
                        El sistema asistencial del Hospital de Ubaté opera en estricta conformidad con la legislación sanitaria y de protección de la información de la República de Colombia:
                    </p>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <h3 class="h6 fw-bold text-dark mb-1">Resolución 1995 de 1999</h3>
                                <p class="small text-muted mb-0">
                                    Normas para el diligenciamiento, custodia, confidencialidad e inviolabilidad de la Historia Clínica electrónica y física.
                                </p>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <h3 class="h6 fw-bold text-dark mb-1">Resolución 5596 de 2015</h3>
                                <p class="small text-muted mb-0">
                                    Criterios técnicos obligatorios de selección y clasificación de pacientes en los servicios de urgencias (Triage I al V).
                                </p>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="p-3 bg-light rounded-3 border h-100">
                                <h3 class="h6 fw-bold text-dark mb-1">Ley 1581 de 2012</h3>
                                <p class="small text-muted mb-0">
                                    Protección de datos personales, habeas data y tratamiento estrictamente confidencial de datos sensibles de salud.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Deberes y Derechos del Paciente --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold text-dark mb-3">
                        <i class="bi bi-people text-primary me-2" aria-hidden="true"></i>Derechos y Deberes en el Servicio de Urgencias
                    </h2>
                    <div class="row g-4">
                        <div class="col-12 col-md-6">
                            <h3 class="h6 fw-bold text-dark border-bottom pb-2">Sus Derechos</h3>
                            <ul class="list-unstyled small text-secondary">
                                <li class="mb-2"><i class="bi bi-check2 text-primary me-2"></i>Recibir atención médica de urgencias sin discriminación alguna.</li>
                                <li class="mb-2"><i class="bi bi-check2 text-primary me-2"></i>Ser clasificado oportunamente mediante Triage según su gravedad.</li>
                                <li class="mb-2"><i class="bi bi-check2 text-primary me-2"></i>Ser informado sobre su diagnóstico, procedimientos y evolución médica.</li>
                                <li class="mb-2"><i class="bi bi-check2 text-primary me-2"></i>Autorizar o revocar el acceso a la información médica para su acompañante.</li>
                            </ul>
                        </div>
                        <div class="col-12 col-md-6">
                            <h3 class="h6 fw-bold text-dark border-bottom pb-2">Sus Deberes</h3>
                            <ul class="list-unstyled small text-secondary">
                                <li class="mb-2"><i class="bi bi-arrow-right text-secondary me-2"></i>Suministrar información veraz y completa sobre sus antecedentes y síntomas.</li>
                                <li class="mb-2"><i class="bi bi-arrow-right text-secondary me-2"></i>Respetar el orden de atención establecido por el Triage clínico.</li>
                                <li class="mb-2"><i class="bi bi-arrow-right text-secondary me-2"></i>Tratar con dignidad y respeto al personal médico y asistencial.</li>
                                <li class="mb-2"><i class="bi bi-arrow-right text-secondary me-2"></i>Cuidar las instalaciones y recursos físicos del hospital.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Llamado a la acción --}}
            <div class="text-center py-3">
                <a href="{{ route('home') }}" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver al Inicio
                </a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">
                        <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Panel Principal
                    </a>
                @else
                    <a href="{{ route('contact.create') }}" class="btn btn-primary">
                        <i class="bi bi-envelope me-1" aria-hidden="true"></i>Canal de Contacto
                    </a>
                @endauth

            </div>
        </div>
    </div>
</div>
@endsection