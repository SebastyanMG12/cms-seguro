@extends('layouts.app')

@section('title', 'Registro de Emergencia: Paciente NN')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        {{-- Navegación superior --}}
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2 small">
                <li class="breadcrumb-item"><a href="{{ route('episodes.index') }}" class="text-decoration-none">Urgencias</a></li>
                <li class="breadcrumb-item active" aria-current="page">Registro de Emergencia (NN)</li>
            </ol>
        </nav>

        {{-- Encabezado --}}
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div>
                <h1 class="h4 mb-0 fw-bold text-dark">
                    <i class="bi bi-person-exclamation text-danger me-2" aria-hidden="true"></i>Registro de Emergencia: Paciente Sin Identificar (NN)
                </h1>
                <p class="text-muted small mb-0">Atención médica inmediata para pacientes sin capacidad de identificación o inconscientes.</p>
            </div>
            <span class="badge bg-danger-subtle text-danger border border-danger font-monospace px-3 py-2 fs-6 tabular-nums">
                {{ $nextNnCode }}
            </span>
        </div>

        {{-- Banner de Tratamiento sin Consentimiento Previo por Interés Vital --}}
        <div class="alert alert-warning border-warning py-3 px-3 mb-4 shadow-sm" role="alert">
            <div class="d-flex align-items-start">
                <i class="bi bi-shield-exclamation text-warning fs-4 me-3 mt-1" aria-hidden="true"></i>
                <div class="small">
                    <strong class="d-block text-dark mb-1">Tratamiento sin Consentimiento Previo: Interés Vital del Titular</strong>
                    <span>De conformidad con el <strong>Artículo 10 Literal B de la Ley 1581 de 2012</strong> y el Decreto 1377 de 2013, la atención médica de urgencia a personas en estado de incapacidad física o legal no requiere autorización previa cuando esté de por medio el interés vital del paciente. No se exige documento, nombres ni firma previa para salvaguardar la vida.</span>
                </div>
            </div>
        </div>

        {{-- Formulario de Registro NN --}}
        <div class="card border shadow-sm">
            <div class="card-header bg-light py-2 px-3 border-bottom">
                <strong class="small text-dark">
                    <i class="bi bi-card-checklist me-1 text-primary" aria-hidden="true"></i>Datos Mínimos de Admisión Inmediata
                </strong>
            </div>
            <div class="card-body p-3 p-md-4">
                <form method="POST" action="{{ route('episodes.nn.store') }}" novalidate>
                    @csrf

                    {{-- 1. Identificador y Sexo Aparente --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="nn_code_display" class="form-label small fw-semibold">Código Temporal Asignado</label>
                            <input type="text" class="form-control form-control-sm font-monospace tabular-nums bg-light" id="nn_code_display" value="{{ $nextNnCode }}" readonly>
                            <div class="form-text small">Identificador único correlativo del día para trazabilidad clínica.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="apparent_sex" class="form-label small fw-semibold">
                                Sexo Aparente <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm @error('apparent_sex') is-invalid @enderror" id="apparent_sex" name="apparent_sex" required>
                                <option value="">Seleccione sexo aparente...</option>
                                <option value="masculino" {{ old('apparent_sex') === 'masculino' ? 'selected' : '' }}>Masculino</option>
                                <option value="femenino" {{ old('apparent_sex') === 'femenino' ? 'selected' : '' }}>Femenino</option>
                                <option value="indeterminado" {{ old('apparent_sex') === 'indeterminado' ? 'selected' : '' }}>Indeterminado / No precisado</option>
                            </select>
                            @error('apparent_sex')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- 2. Edad Estimada y Forma de Llegada --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="estimated_age" class="form-label small fw-semibold">
                                Edad Aproximada / Grupo Etario <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm @error('estimated_age') is-invalid @enderror" id="estimated_age" name="estimated_age" value="{{ old('estimated_age') }}" placeholder="Ej: Aprox. 35 - 40 años / Adulto mayor" required maxlength="60">
                            @error('estimated_age')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text small">Apreciación física preliminar del profesional asistencial.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="arrival_mode" class="form-label small fw-semibold">
                                Forma de Llegada a Urgencias <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm @error('arrival_mode') is-invalid @enderror" id="arrival_mode" name="arrival_mode" required>
                                <option value="">Seleccione medio de transporte/llegada...</option>
                                <option value="ambulancia" {{ old('arrival_mode') === 'ambulancia' ? 'selected' : '' }}>Ambulancia / Misión Médica</option>
                                <option value="policia" {{ old('arrival_mode') === 'policia' ? 'selected' : '' }}>Patrulla de Policía / Autoridad</option>
                                <option value="bomberos" {{ old('arrival_mode') === 'bomberos' ? 'selected' : '' }}>Cuerpo de Bomberos / Defensa Civil</option>
                                <option value="transeunte" {{ old('arrival_mode') === 'transeunte' ? 'selected' : '' }}>Transeúnte / Tercero circunstancial</option>
                                <option value="propio_pie" {{ old('arrival_mode') === 'propio_pie' ? 'selected' : '' }}>Por sus propios medios (desorientado/sin documento)</option>
                                <option value="otro" {{ old('arrival_mode') === 'otro' ? 'selected' : '' }}>Otro transporte</option>
                            </select>
                            @error('arrival_mode')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- 3. Quién lo Trajo y Contacto / Placa --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label for="brought_by_name" class="form-label small fw-semibold">
                                Quién lo Trajo / Remitente <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control form-control-sm @error('brought_by_name') is-invalid @enderror" id="brought_by_name" name="brought_by_name" value="{{ old('brought_by_name') }}" placeholder="Ej: Ambulancia SAMU Móvil 12 / Patrulla Cuadrante 4" required maxlength="150">
                            @error('brought_by_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text small">Institución, patrulla, número de tripulación o nombre de quien entrega.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="brought_by_contact" class="form-label small fw-semibold">
                                Identificación / Placa / Contacto de Entrega
                            </label>
                            <input type="text" class="form-control form-control-sm @error('brought_by_contact') is-invalid @enderror" id="brought_by_contact" name="brought_by_contact" value="{{ old('brought_by_contact') }}" placeholder="Ej: Placa OBF-123 / Celular 3101234567 / Placa policía" maxlength="150">
                            @error('brought_by_contact')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- 4. Señas Particulares, Tatuajes y Vestimenta --}}
                    <div class="mb-3">
                        <label for="distinguishing_marks" class="form-label small fw-semibold">
                            Señas Particulares, Vestimenta y Pertenencias <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control form-control-sm @error('distinguishing_marks') is-invalid @enderror" id="distinguishing_marks" name="distinguishing_marks" rows="3" placeholder="Describa tatuajes visibles, cicatrices, contextura física, color de piel/cabello, vestimenta al ingreso (chaqueta, pantalón, calzado) y pertenencias relevantes para futura identificación." required minlength="5" maxlength="2000">{{ old('distinguishing_marks') }}</textarea>
                        @error('distinguishing_marks')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text small">Fundamental para el posterior cotejo en la conciliación de identidad o con Medicina Legal.</div>
                    </div>

                    {{-- 5. Motivo de Urgencia / Hallazgo Clínico --}}
                    <div class="mb-3">
                        <label for="reason" class="form-label small fw-semibold">
                            Motivo Aparente de Urgencia / Hallazgo Inicial <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control form-control-sm @error('reason') is-invalid @enderror" id="reason" name="reason" rows="2" placeholder="Ej: Paciente inconsciente hallado en vía pública tras presunto trauma encéfalo-craneano por atropello vial." required minlength="5" maxlength="500">{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- 6. Observaciones Iniciales --}}
                    <div class="mb-4">
                        <label for="initial_notes" class="form-label small fw-semibold">Observaciones Clínicas Iniciales (Opcional)</label>
                        <textarea class="form-control form-control-sm @error('initial_notes') is-invalid @enderror" id="initial_notes" name="initial_notes" rows="2" placeholder="Signos evidentes, estado neurológico inicial (Glasgow aproximado) o antecedentes aportados verbalmente por quien lo entregó." maxlength="1000">{{ old('initial_notes') }}</textarea>
                        @error('initial_notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Botones de Acción --}}
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="{{ route('episodes.index') }}" class="btn btn-outline-secondary btn-sm px-3">
                            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-danger btn-sm px-4 fw-semibold shadow-sm">
                            <i class="bi bi-shield-plus me-1" aria-hidden="true"></i>Registrar Ingreso de Emergencia (NN)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
