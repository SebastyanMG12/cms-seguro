@extends('layouts.app')

@section('title', 'Historia Clínica - Episodio ' . $episode->public_code)

@section('content')
{{-- Encabezado del Episodio Clínico --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('episodes.index') }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Volver
            </a>
            <h1 class="h4 mb-0 fw-bold font-monospace tabular-nums">Episodio: {{ $episode->public_code }}</h1>
            @switch($episode->status)
                @case('preregistrado')
                    <span class="badge bg-secondary"><i class="bi bi-clock me-1" aria-hidden="true"></i>Pre-registrado</span>
                    @break
                @case('en_espera')
                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-top me-1" aria-hidden="true"></i>En espera</span>
                    @break
                @case('en_valoracion')
                    <span class="badge bg-info text-dark"><i class="bi bi-clipboard2-pulse me-1" aria-hidden="true"></i>En valoración</span>
                    @break
                @case('ingresado')
                    <span class="badge bg-primary"><i class="bi bi-hospital me-1" aria-hidden="true"></i>Ingresado</span>
                    @break
                @case('egresado')
                    <span class="badge bg-success"><i class="bi bi-check-all me-1" aria-hidden="true"></i>Egresado</span>
                    @break
                @case('expirado')
                    <span class="badge bg-dark"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>Expirado</span>
                    @break
            @endswitch
        </div>
        <small class="text-muted">Origen: {{ ucfirst(str_replace('_', '-', $episode->origin)) }} | Creado: <span class="tabular-nums">{{ $episode->created_at->format('d/m/Y H:i') }}</span></small>
    </div>

    @if ($episode->isClosed())
        <div class="episode-closed-banner">
            <i class="bi bi-lock-fill me-1 text-secondary" aria-hidden="true"></i> Episodio cerrado (Solo lectura legal)
        </div>
    @endif
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
        <i class="bi bi-check-circle me-1" aria-hidden="true"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

@if (!empty($exceptionalReason))
    <div class="alert alert-warning border border-warning d-flex align-items-center mb-3 py-2 px-3 shadow-sm" role="alert">
        <i class="bi bi-shield-exclamation fs-4 me-2 text-warning-emphasis" aria-hidden="true"></i>
        <div class="small">
            <strong>Acceso Excepcional Registrado:</strong> Usted está consultando esta historia clínica bajo la causal:
            <span class="fst-italic fw-bold text-dark">"{{ $exceptionalReason }}"</span>.
            Todo acceso queda auditado de manera inmutable de conformidad con la Ley 1581 de 2012 y la Res. 1995 de 1999.
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger py-2" role="alert">
        <ul class="mb-0 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Banner de Estado de Paciente NN y Conciliación de Identidad (Fase 4B) --}}
@if ($episode->is_nn)
    @if ($episode->isReconciled())
        <div class="alert alert-success border-success py-2 px-3 small mb-3 shadow-sm" role="alert">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <i class="bi bi-patch-check-fill text-success fs-5 me-2" aria-hidden="true"></i>
                    <strong>Identidad civil conciliada y verificada:</strong> 
                    Vinculado formalmente a <strong>{{ $episode->patient?->full_name }}</strong> el <span class="tabular-nums">{{ $episode->reconciled_at?->format('d/m/Y H:i') }}</span> por {{ $episode->reconciledByUser?->name }}.
                    <span class="text-muted font-monospace ms-2">(Código original de ingreso: {{ $episode->nn_code }})</span>
                </div>
                <span class="badge bg-success font-monospace px-2 py-1"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Conciliado</span>
            </div>
            <div class="mt-2 p-2 bg-white rounded border border-success-subtle small text-dark">
                <strong><i class="bi bi-file-earmark-check me-1 text-success" aria-hidden="true"></i>Evidencia de verificación registrada:</strong>
                {{ $episode->reconciliation_evidence }}
            </div>
        </div>
    @else
        <div class="alert alert-warning border-warning py-2 px-3 small mb-3 shadow-sm d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
            <div>
                <i class="bi bi-shield-exclamation text-warning fs-5 me-2" aria-hidden="true"></i>
                <strong>Paciente Ingresado como NN ({{ $episode->nn_code }}):</strong>
                Atención médica inmediata sin consentimiento previo por interés vital (Ley 1581 de 2012 Art. 10).
            </div>
            @can('reconcile', $episode)
                <a href="{{ route('episodes.reconcile.create', $episode) }}" class="btn btn-sm btn-primary py-1 px-3 shadow-sm fw-semibold">
                    <i class="bi bi-person-check me-1" aria-hidden="true"></i>Conciliar Identidad
                </a>
            @endcan
        </div>
    @endif
@endif

{{-- Barra de Acciones Clínicas por Estado (Regla Sección 7.3) --}}
<div class="card shadow-sm border mb-3 bg-light">
    <div class="card-body p-2 d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="d-flex flex-wrap gap-2">

            {{-- 1. Registrar Llegada --}}
            @if ($episode->status === 'preregistrado')
                @can('episodios.llegada')
                    <form method="POST" action="{{ route('episodes.arrival', $episode) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-warning fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Registrar Llegada
                        </button>
                    </form>
                @endcan
            @else
                <span class="action-disabled-wrapper" title="La llegada ya fue registrada anteriormente.">
                    <button class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-check me-1" aria-hidden="true"></i> Llegada Registrada
                    </button>
                </span>
            @endif

            {{-- 2. Asignar / Modificar Triage --}}
            @if (in_array($episode->status, ['en_espera', 'en_valoracion']))
                @can('episodios.triage')
                    <button type="button" class="btn btn-sm btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#triageModal">
                        <i class="bi bi-clipboard2-pulse me-1" aria-hidden="true"></i> Asignar Triage
                    </button>
                @endcan
            @else
                <span class="action-disabled-wrapper" title="{{ $episode->isClosed() ? 'Episodio cerrado.' : 'Triage completado e ingreso formalizado.' }}">
                    <button class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-clipboard2-pulse me-1" aria-hidden="true"></i> Triage
                    </button>
                </span>
            @endif

            {{-- 3. Confirmar Ingreso --}}
            @if ($episode->status === 'en_valoracion')
                @can('episodios.ingreso')
                    <form method="POST" action="{{ route('episodes.admission', $episode) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success fw-semibold">
                            <i class="bi bi-door-open me-1" aria-hidden="true"></i> Confirmar Ingreso
                        </button>
                    </form>
                @endcan
            @else
                <span class="action-disabled-wrapper" title="{{ $episode->status === 'preregistrado' || $episode->status === 'en_espera' ? 'Debe asignar triage antes de confirmar ingreso.' : 'Paciente ya ingresado o egresado.' }}">
                    <button class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-door-open me-1" aria-hidden="true"></i> Confirmar Ingreso
                    </button>
                </span>
            @endif

            {{-- 4. Asignar Camilla (Solo disponible cuando el paciente está ingresado) --}}
            @if ($episode->status === 'ingresado')
                @can('assignBed', $episode)
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bedModal">
                        <i class="bi bi-hospital me-1" aria-hidden="true"></i> {{ $episode->activeBedAssignment ? 'Cambiar Camilla' : 'Asignar Camilla' }}
                    </button>
                @else
                    <span class="action-disabled-wrapper" title="Solo el personal asistencial asignado o la coordinación pueden gestionar la camilla.">
                        <button class="btn btn-sm btn-outline-secondary" disabled>
                            <i class="bi bi-hospital me-1" aria-hidden="true"></i> {{ $episode->activeBedAssignment ? 'Cambiar Camilla' : 'Asignar Camilla' }}
                        </button>
                    </span>
                @endcan
            @else
                <span class="action-disabled-wrapper" title="Solo se puede asignar camilla a pacientes ingresados.">
                    <button class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-hospital me-1" aria-hidden="true"></i> Asignar Camilla
                    </button>
                </span>
            @endif

            {{-- 5. Asignar Personal Asistencial --}}
            @if ($episode->isOpen())
                @can('episodios.asignar_personal')
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#staffModal">
                        <i class="bi bi-person-plus me-1" aria-hidden="true"></i> Asignar Personal
                    </button>
                @endcan
            @endif

            {{-- 6. Registrar Egreso (Solo si está ingresado) --}}
            @if ($episode->status === 'ingresado')
                @can('discharge', $episode)
                    <button type="button" class="btn btn-sm btn-danger fw-semibold" data-bs-toggle="modal" data-bs-target="#dischargeModal">
                        <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Registrar Egreso
                    </button>
                @else
                    <span class="action-disabled-wrapper" title="Solo el médico responsable asignado o la coordinación médica pueden dar el egreso al paciente.">
                        <button class="btn btn-sm btn-outline-secondary" disabled>
                            <i class="bi bi-lock me-1" aria-hidden="true"></i> Registrar Egreso
                        </button>
                    </span>
                @endcan
            @else
                <span class="action-disabled-wrapper" title="Solo se puede egresar un paciente en estado ingresado.">
                    <button class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i> Registrar Egreso
                    </button>
                </span>
            @endif

        </div>

        <div>
            @if ($episode->triage_level)
                @php
                    $triageClass = match($episode->triage_level) {
                        'I' => 'triage-badge-1',
                        'II' => 'triage-badge-2',
                        'III' => 'triage-badge-3',
                        'IV' => 'triage-badge-4',
                        'V' => 'triage-badge-5',
                        default => 'bg-secondary text-white',
                    };
                    $triageLabel = match($episode->triage_level) {
                        'I' => 'Resucitación',
                        'II' => 'Emergencia',
                        'III' => 'Urgencia',
                        'IV' => 'Prioritario',
                        'V' => 'No urgente',
                        default => '',
                    };
                @endphp
                <span class="badge {{ $triageClass }} px-3 py-2 fs-6">
                    Triage Nivel {{ $episode->triage_level }}: {{ $triageLabel }}
                </span>
            @endif
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Columna Izquierda: Información del Paciente y Censo --}}
    <div class="col-lg-4">

        {{-- Datos del Paciente --}}
        <div class="card shadow-sm mb-3 card-compact">
            <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0 fw-bold"><i class="bi bi-person me-1" aria-hidden="true"></i>Ficha del Paciente</h2>
                <span class="badge bg-light text-dark border small tabular-nums">{{ $episode->patient?->identity_status }}</span>
            </div>
            <div class="card-body p-3">
                <p class="h5 fw-bold mb-1">{{ $episode->patient?->full_name ?? 'Sin identificación' }}</p>
                <div class="text-muted small mb-2 font-monospace tabular-nums">
                    {{ $episode->patient?->document_type ?? 'Documento' }}: {{ $episode->patient?->document_number ?? 'No registrado' }}
                </div>
                <div class="small mb-1">
                    <span class="text-muted">Teléfono:</span> <span class="tabular-nums">{{ $episode->patient?->phone ?? 'No registrado' }}</span>
                </div>
                @if ($episode->patient?->birth_date)
                    <div class="small mb-1">
                        <span class="text-muted">Nacimiento:</span> <span class="tabular-nums">{{ $episode->patient->birth_date->format('d/m/Y') }}</span>
                    </div>
                @endif
                <div class="small mb-1">
                    <span class="text-muted">Sexo:</span> {{ ucfirst($episode->patient?->gender ?? 'No especificado') }}
                </div>

                @if ($episode->is_nn)
                    <div class="p-2 bg-light border rounded small mt-2 mb-2">
                        <strong class="d-block text-dark mb-1"><i class="bi bi-person-exclamation me-1 text-danger" aria-hidden="true"></i>Datos de Admisión de Urgencia (NN):</strong>
                        <div class="row g-1">
                            <div class="col-6"><span class="text-muted">Sexo aparente:</span> <span class="text-capitalize">{{ $episode->apparent_sex }}</span></div>
                            <div class="col-6"><span class="text-muted">Edad estimada:</span> {{ $episode->estimated_age }}</div>
                            <div class="col-12"><span class="text-muted">Medio llegada:</span> {{ ucfirst($episode->arrival_mode) }} &mdash; {{ $episode->brought_by_name }}</div>
                            @if ($episode->distinguishing_marks)
                                <div class="col-12 mt-1">
                                    <span class="text-muted d-block">Señas/Vestimenta:</span>
                                    <span class="text-dark">{{ $episode->distinguishing_marks }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <hr class="my-2">

                {{-- Datos Clínicos Relevantes --}}
                <div class="small mb-1">
                    <span class="text-muted">Grupo Sanguíneo:</span> <strong>{{ $episode->clinicalData?->blood_type ?? 'Desconocido' }}</strong>
                </div>

                <div class="small mb-2">
                    <span class="text-muted">Alergias a Medicamentos:</span>
                    @if (($episode->clinicalData?->allergies_type ?? '') === 'si')
                        <span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>SÍ: {{ $episode->clinicalData?->allergies_detail }}</span>
                    @elseif (($episode->clinicalData?->allergies_type ?? '') === 'ninguna')
                        <span class="badge bg-success ms-1">Ninguna conocida</span>
                    @else
                        <span class="badge bg-secondary ms-1">No sabe</span>
                    @endif
                </div>

                <div class="small mb-2">
                    <span class="text-muted fw-bold d-block">Motivo de Consulta:</span>
                    <div class="p-2 bg-light border rounded">{{ $episode->reason }}</div>
                </div>

                @if ($episode->clinicalData?->warning_signs && count($episode->clinicalData->warning_signs) > 0)
                    <div class="alert alert-danger py-1 px-2 small mb-0">
                        <strong><i class="bi bi-exclamation-octagon-fill me-1" aria-hidden="true"></i>Signos de Alarma Marcados:</strong>
                        <ul class="mb-0 ps-3">
                            @foreach ($episode->clinicalData->warning_signs as $sign)
                                <li>{{ ucfirst(str_replace('_', ' ', $sign)) }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        {{-- Ubicación Física y Personal Asignado --}}
        <div class="card shadow-sm mb-3 card-compact">
            <div class="card-header bg-white py-2 px-3">
                <h3 class="h6 mb-0 fw-bold"><i class="bi bi-hospital me-1" aria-hidden="true"></i>Ubicación y Asistenciales</h3>
            </div>
            <div class="card-body p-3">
                <div class="mb-2">
                    <span class="text-muted small d-block">Camilla Asignada:</span>
                    @if ($episode->activeBedAssignment)
                        <span class="badge bg-success font-monospace tabular-nums fs-6">
                            <i class="bi bi-door-closed me-1" aria-hidden="true"></i>{{ $episode->activeBedAssignment->bed->code }}
                        </span>
                        <small class="text-muted d-block mt-1">Habitación {{ $episode->activeBedAssignment->bed->room->code }}</small>
                    @else
                        <span class="text-muted small">Sin camilla asignada</span>
                    @endif
                </div>

                <hr class="my-2">

                <div class="mb-2">
                    <span class="text-muted small d-block">Médico Responsable:</span>
                    @if ($episode->responsibleUser)
                        <strong>{{ $episode->responsibleUser->name }}</strong>
                        <small class="text-muted d-block font-monospace tabular-nums">{{ $episode->responsibleUser->staffProfile?->code }} - {{ $episode->responsibleUser->staffProfile?->specialty }}</small>
                    @else
                        <span class="text-muted small">Sin médico responsable asignado</span>
                    @endif
                </div>

                @if ($episode->activeAssignments->where('role', 'apoyo')->count() > 0)
                    <div class="mb-0">
                        <span class="text-muted small d-block mb-1">Personal de Apoyo:</span>
                        <ul class="list-unstyled mb-0 small">
                            @foreach ($episode->activeAssignments->where('role', 'apoyo') as $asg)
                                <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                    <div>
                                        <i class="bi bi-person-check text-primary" aria-hidden="true"></i> {{ $asg->user->name }}
                                        <small class="text-muted font-monospace tabular-nums">({{ $asg->user->staffProfile?->code }})</small>
                                    </div>
                                    @can('endStaffAssignment', [$episode, $asg])
                                        <form method="POST" action="{{ route('episodes.staff.end', [$episode, $asg]) }}" class="d-inline" onsubmit="return confirm('¿Desea finalizar la participación de apoyo de {{ $asg->user->name }} en este episodio?');">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.75rem;" title="Desactivar / Finalizar función de apoyo">
                                                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Desactivar
                                            </button>
                                        </form>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        {{-- Acompañante o Representante --}}
        <div class="card shadow-sm mb-3 card-compact">
            <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-bold"><i class="bi bi-people me-1" aria-hidden="true"></i>Acompañante</h3>
                @if ($episode->isOpen())
                    <button class="btn btn-sm btn-link py-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#companionModal">
                        <i class="bi bi-pencil" aria-hidden="true"></i> Editar
                    </button>
                @endif
            </div>
            <div class="card-body p-3 small">
                @if ($episode->companion)
                    <div><strong>{{ $episode->companion->name }}</strong></div>
                    <div class="text-muted">Parentesco: {{ $episode->companion->relationship ?? 'No especificado' }}</div>
                    <div class="text-muted tabular-nums">Teléfono: {{ $episode->companion->phone ?? 'No registrado' }}</div>
                @else
                    <span class="text-muted">Sin datos de acompañante registrados.</span>
                @endif
            </div>
        </div>

    </div>

    {{-- Columna Derecha: Historia Clínica y Entradas Inmutables --}}
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0 fw-bold text-dark"><i class="bi bi-journal-medical me-1" aria-hidden="true"></i>Evolución y Entradas Clínicas (Inmutables)</h2>
                @if (in_array($episode->status, ['en_valoracion', 'ingresado']))
                    @can('addClinicalEntry', $episode)
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#entryModal">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Registrar Entrada Clínica
                        </button>
                    @else
                        <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Solo el personal asistencial asignado a este paciente o la coordinación médica pueden registrar evoluciones clínicas.">
                            <button class="btn btn-sm btn-outline-secondary" disabled>
                                <i class="bi bi-lock me-1" aria-hidden="true"></i> Registrar Entrada Clínica
                            </button>
                        </span>
                    @endcan
                @elseif ($episode->isOpen())
                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="El paciente debe encontrarse en valoración o ingresado para registrar notas clínicas o diagnósticos.">
                        <button class="btn btn-sm btn-outline-secondary" disabled>
                            <i class="bi bi-lock me-1" aria-hidden="true"></i> Registrar Entrada Clínica
                        </button>
                    </span>
                @else
                    {{-- Si el episodio está cerrado, la única acción admitida es aclaración posterior --}}
                    @can('addClinicalEntry', $episode)
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#clarificationModal">
                            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Agregar Aclaración Posterior
                        </button>
                    @else
                        <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="Solo el personal asistencial asignado o la coordinación pueden agregar aclaraciones.">
                            <button class="btn btn-sm btn-outline-secondary" disabled>
                                <i class="bi bi-lock me-1" aria-hidden="true"></i> Agregar Aclaración Posterior
                            </button>
                        </span>
                    @endcan
                @endif
            </div>
            <div class="card-body p-3">
                <div class="timeline">
                    @forelse ($episode->clinicalEntries as $entry)
                        @php
                            $isAnnulled = $entry->isAnnulled();
                        @endphp
                        <div class="p-3 mb-3 border rounded {{ $isAnnulled ? 'bg-light text-muted opacity-75' : 'bg-white shadow-sm' }}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    @switch($entry->type)
                                        @case('diagnostico')
                                            <span class="badge bg-danger-subtle text-danger border"><i class="bi bi-heart-pulse me-1" aria-hidden="true"></i>Diagnóstico</span>
                                            @break
                                        @case('procedimiento')
                                            <span class="badge bg-primary-subtle text-primary border"><i class="bi bi-bandaid me-1" aria-hidden="true"></i>Procedimiento</span>
                                            @break
                                        @case('nota')
                                            <span class="badge bg-info-subtle text-dark border"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>Nota de Evolución</span>
                                            @break
                                        @case('aclaracion')
                                            <span class="badge bg-warning-subtle text-warning-emphasis border"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Aclaración</span>
                                            @break
                                        @case('anulacion')
                                            <span class="badge bg-dark text-white"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Anulación</span>
                                            @break
                                    @endswitch
                                    <span class="small text-muted ms-2 tabular-nums">{{ $entry->performed_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="small text-muted">
                                    Por: <strong>{{ $entry->author->name }}</strong> <span class="font-monospace tabular-nums">({{ $entry->author->staffProfile?->code }})</span>
                                </div>
                            </div>

                            <div class="mb-2 {{ $isAnnulled ? 'text-decoration-line-through' : '' }}">
                                {{ $entry->content }}
                            </div>

                            @if ($entry->reason)
                                <div class="small p-2 bg-warning-subtle border border-warning rounded mb-2">
                                    <strong>Motivo justificado:</strong> {{ $entry->reason }}
                                </div>
                            @endif

                            {{-- Mostrar anulaciones vinculadas --}}
                            @if ($isAnnulled)
                                <div class="alert alert-danger py-1 px-2 small mb-2">
                                    <i class="bi bi-exclamation-octagon-fill me-1" aria-hidden="true"></i>
                                    <strong>Entrada Anulada:</strong>
                                    @foreach ($entry->annulments as $ann)
                                        {{ $ann->reason }} (por {{ $ann->author->name }} el {{ $ann->performed_at->format('d/m/Y H:i') }})
                                    @endforeach
                                </div>
                            @endif

                            {{-- Mostrar aclaraciones vinculadas --}}
                            @if ($entry->clarifications->count() > 0)
                                <div class="ms-3 border-start ps-3 mt-2 border-warning">
                                    <span class="text-uppercase small fw-bold text-warning-emphasis d-block">Notas Aclaratorias:</span>
                                    @foreach ($entry->clarifications as $clar)
                                        <div class="small bg-light p-2 rounded mb-1 border">
                                            <strong>{{ $clar->performed_at->format('d/m/Y H:i') }} ({{ $clar->author->name }}):</strong>
                                            {{ $clar->content }}
                                            <div class="text-muted fst-italic">Motivo: {{ $clar->reason }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Botones de Aclarar y Anular (sin botón de editar, Res. 1995 de 1999) --}}
                            @if (! $isAnnulled && $entry->type !== 'anulacion')
                                <div class="text-end border-top pt-2 mt-2">
                                    <button class="btn btn-sm btn-link text-warning-emphasis p-0 text-decoration-none me-2" data-bs-toggle="modal" data-bs-target="#clarifyEntryModal{{ $entry->id }}">
                                        <i class="bi bi-pencil-square" aria-hidden="true"></i> Aclarar
                                    </button>
                                    @if ($episode->isOpen())
                                        <button class="btn btn-sm btn-link text-danger p-0 text-decoration-none" data-bs-toggle="modal" data-bs-target="#annulEntryModal{{ $entry->id }}">
                                            <i class="bi bi-slash-circle" aria-hidden="true"></i> Anular
                                        </button>
                                    @endif
                                </div>

                                {{-- Modal para Aclarar Entrada Específica --}}
                                <div class="modal fade" id="clarifyEntryModal{{ $entry->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('episodes.entries.store', $episode) }}">
                                                @csrf
                                                <input type="hidden" name="type" value="aclaracion">
                                                <input type="hidden" name="references_entry_id" value="{{ $entry->id }}">
                                                <div class="modal-header">
                                                    <h5 class="modal-title h6">Agregar Nota Aclaratoria</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-2">
                                                        <label class="form-label small fw-semibold">Contenido de la Aclaración <span class="text-danger">*</span></label>
                                                        <textarea class="form-control form-control-sm" name="content" rows="3" required></textarea>
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small fw-semibold">Motivo Justificado de la Aclaración <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control form-control-sm" name="reason" minlength="5" required placeholder="Ej: Corrección de dosis, precisión de síntoma">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-sm btn-warning">Guardar Aclaración</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                {{-- Modal para Anular Entrada Específica --}}
                                <div class="modal fade" id="annulEntryModal{{ $entry->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('episodes.entries.store', $episode) }}">
                                                @csrf
                                                <input type="hidden" name="type" value="anulacion">
                                                <input type="hidden" name="references_entry_id" value="{{ $entry->id }}">
                                                <div class="modal-header">
                                                    <h5 class="modal-title h6 text-danger">Anular Entrada Clínica</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="small text-muted">
                                                        La anulación conservará visible el texto original pero lo marcará como anulado en la historia clínica inalterable.
                                                    </p>
                                                    <div class="mb-2">
                                                        <label class="form-label small fw-semibold">Declaración de Anulación <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control form-control-sm" name="content" required placeholder="Ej: Se anula por error de digitación en paciente equivocado">
                                                    </div>
                                                    <div class="mb-2">
                                                        <label class="form-label small fw-semibold">Motivo Legal Obligatorio <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control form-control-sm" name="reason" minlength="5" required placeholder="Mínimo 5 caracteres">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-sm btn-danger">Confirmar Anulación</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-file-earmark-medical fs-2 d-block mb-2" aria-hidden="true"></i>
                            Aún no se han registrado evoluciones ni diagnósticos clínicos en este episodio.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODALES DE GESTIÓN --}}

{{-- Modal Asignar Triage --}}
<div class="modal fade" id="triageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('episodes.triage', $episode) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title h6">Clasificación de Triage (Resolución 5596 de 2015)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Seleccione el Nivel de Triage <span class="text-danger">*</span></label>
                        <select class="form-select" name="triage_level" required>
                            <option value="">-- Seleccionar Nivel --</option>
                            <option value="I" {{ $episode->triage_level === 'I' ? 'selected' : '' }}>Nivel I - Resucitación (Atención Inmediata)</option>
                            <option value="II" {{ $episode->triage_level === 'II' ? 'selected' : '' }}>Nivel II - Emergencia (Hasta 30 minutos)</option>
                            <option value="III" {{ $episode->triage_level === 'III' ? 'selected' : '' }}>Nivel III - Urgencia (Hasta 45 minutos)</option>
                            <option value="IV" {{ $episode->triage_level === 'IV' ? 'selected' : '' }}>Nivel IV - Prioritario (Consulta)</option>
                            <option value="V" {{ $episode->triage_level === 'V' ? 'selected' : '' }}>Nivel V - No Urgente (Ambulatorio)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">Guardar Triage</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Asignar Camilla --}}
<div class="modal fade" id="bedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('episodes.bed.assign', $episode) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title h6">Asignación de Camilla Hospitalaria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Camilla Disponible <span class="text-danger">*</span></label>
                        <select class="form-select" name="bed_id" required>
                            <option value="">-- Seleccionar Camilla Disponible --</option>
                            @foreach ($availableBeds as $bed)
                                <option value="{{ $bed->id }}">Camilla {{ $bed->code }} - Habitación {{ $bed->room->code }} ({{ $bed->room->area?->name }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">Asignar Camilla</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Asignar Personal --}}
<div class="modal fade" id="staffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('episodes.staff.assign', $episode) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title h6">Asignación de Personal Asistencial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($episode->activeAssignments->where('role', 'apoyo')->count() > 0)
                        <div class="mb-3 p-2 bg-light border rounded">
                            <span class="text-muted small d-block fw-semibold mb-2">Personal de Apoyo Activo:</span>
                            <ul class="list-unstyled mb-0 small">
                                @foreach ($episode->activeAssignments->where('role', 'apoyo') as $asg)
                                    <li class="d-flex justify-content-between align-items-center py-1 border-bottom border-light">
                                        <span>
                                            <i class="bi bi-person-check text-primary me-1" aria-hidden="true"></i>{{ $asg->user->name }}
                                            <span class="text-muted font-monospace tabular-nums">({{ $asg->user->staffProfile?->code }})</span>
                                        </span>
                                        @can('endStaffAssignment', [$episode, $asg])
                                            <button type="submit" form="endModalStaffForm_{{ $asg->id }}" class="btn btn-outline-danger btn-sm py-0 px-2" style="font-size: 0.75rem;">
                                                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Desactivar
                                            </button>
                                        @endcan
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Profesional en Turno Activo <span class="text-danger">*</span></label>
                        <select class="form-select" name="staff_user_id" required>
                            <option value="">-- Seleccionar Profesional --</option>
                            @foreach ($availableStaff as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->staffProfile?->code }} - {{ $user->staffProfile?->specialty }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Rol en el Episodio <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" required>
                            <option value="responsable">Médico Responsable</option>
                            <option value="apoyo">Personal de Apoyo (Enfermería / Asistencia)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">Guardar Asignación</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach ($episode->activeAssignments->where('role', 'apoyo') as $asg)
    @can('endStaffAssignment', [$episode, $asg])
        <form id="endModalStaffForm_{{ $asg->id }}" method="POST" action="{{ route('episodes.staff.end', [$episode, $asg]) }}" onsubmit="return confirm('¿Desea finalizar la participación de apoyo de {{ $asg->user->name }} en este episodio?');">
            @csrf
        </form>
    @endcan
@endforeach

{{-- Modal Registrar Egreso --}}
<div class="modal fade" id="dischargeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('episodes.discharge', $episode) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title h6 text-danger">Registrar Egreso y Cierre de Episodio</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1" aria-hidden="true"></i> Al registrar el egreso, el episodio quedará <strong>cerrado</strong>, la camilla activa será liberada para desinfección y no se admitirán más ediciones clínicas ordinarias.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tipo de Egreso <span class="text-danger">*</span></label>
                        <select class="form-select" name="discharge_type" required>
                            <option value="">-- Seleccionar Tipo de Egreso --</option>
                            <option value="alta_medica">Alta Médica</option>
                            <option value="remision">Remisión a otra Institución</option>
                            <option value="alta_voluntaria">Alta Voluntaria</option>
                            <option value="abandono">Abandono de la Institución</option>
                            <option value="fallecimiento">Fallecimiento</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger">Confirmar Egreso</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Registrar Entrada Clínica Ordinaria --}}
<div class="modal fade" id="entryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('episodes.entries.store', $episode) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title h6">Nueva Entrada Clínica Inmutable</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tipo de Entrada <span class="text-danger">*</span></label>
                        <select class="form-select" name="type" required>
                            <option value="diagnostico">Diagnóstico</option>
                            <option value="procedimiento">Procedimiento</option>
                            <option value="nota">Nota de Evolución</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Contenido Clínico <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="content" rows="4" required minlength="3" maxlength="5000" placeholder="Detalle clínico de la evolución, diagnóstico o procedimiento realizado"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">Registrar en Historia</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Editar Acompañante --}}
<div class="modal fade" id="companionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('episodes.companion.update', $episode) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title h6">Datos del Acompañante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="name" value="{{ $episode->companion?->name }}" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Teléfono</label>
                        <input type="tel" class="form-control form-control-sm" name="phone" value="{{ $episode->companion?->phone }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Parentesco</label>
                        <input type="text" class="form-control form-control-sm" name="relationship" value="{{ $episode->companion?->relationship }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">Guardar Acompañante</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
