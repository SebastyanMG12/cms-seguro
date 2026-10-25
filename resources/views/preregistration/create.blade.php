@extends('layouts.app')

@section('title', 'Pre-registro de Urgencias - Hospital de Ubaté')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">

        {{-- Encabezado Institucional --}}
        <div class="card shadow-sm border-0 mb-4 bg-primary text-white">
            <div class="card-body p-4 text-center">
                <h1 class="h3 fw-bold mb-2">Hospital El Salvador de Ubaté E.S.E.</h1>
                <p class="mb-0 fs-5">Pre-registro Digital para el Servicio de Urgencias</p>
                <small class="opacity-75">Agilice su recepción al presentarse en la institución</small>
            </div>
        </div>

        {{-- Aviso Permanente Obligatorio (Regla Sección 328) --}}
        <div class="alert alert-warning border-warning shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="fs-2 me-3">⚠️</div>
                <div>
                    <h5 class="alert-heading fw-bold mb-1">Aviso Importante sobre la Atención de Urgencias</h5>
                    <p class="mb-1">
                        El pre-registro digital es un mecanismo exclusivo para <strong>agilizar la captura de datos</strong> y <strong>NO sustituye la valoración médica presencial</strong> ni otorga un turno prioritario.
                    </p>
                    <p class="mb-0 small text-dark">
                        Al presentarse en el hospital, todo paciente es valorado por un profesional de la salud en la sala de <strong>Triage</strong> según la <strong>Resolución 5596 de 2015</strong>, determinando la prioridad según la gravedad clínica del caso.
                    </p>
                </div>
            </div>
        </div>

        {{-- Banner Dinámico de Signos de Alarma --}}
        <div id="alarmSignsAlertBanner" class="alert alert-danger border-danger shadow-sm mb-4 d-none" role="alert">
            <div class="d-flex align-items-center">
                <div class="fs-1 me-3">🚨</div>
                <div>
                    <h5 class="alert-heading fw-bold text-danger mb-1">¡ATENCIÓN INMEDIATA REQUERIDA!</h5>
                    <p class="mb-1 fw-semibold">
                        Ha marcado uno o más signos de alarma críticos. NO espere en casa ni posponga la atención.
                    </p>
                    <p class="mb-0 small">
                        Acuda de inmediato al servicio de urgencias del Hospital El Salvador de Ubaté o comuníquese de forma urgente a la línea nacional de emergencias <strong>123</strong> o a la ambulancia local.
                    </p>
                </div>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <h6 class="fw-bold mb-2">Por favor revise los siguientes errores:</h6>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        <form action="{{ route('preregistro.store') }}" method="POST" id="preregistrationForm" novalidate>
            @csrf

            {{-- Honeypot anti-bot --}}
            <input type="text" name="hospital_contact_time" id="hospital_contact_time" value="" style="display:none !important;" tabindex="-1" autocomplete="off" aria-hidden="true">

            {{-- 1. Signos de Alarma Informativos --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-danger text-white py-3">
                    <h5 class="card-title mb-0 fw-bold">1. Signos de Alarma (Marque si presenta alguno)</h5>
                </div>
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">
                        Si el paciente presenta cualquiera de estas condiciones críticas, el sistema alertará la necesidad de atención hospitalaria inmediata.
                    </p>
                    <div class="row g-3">
                        @php
                            $alarmOptions = [
                                'dolor_pecho' => 'Dolor u opresión intensa y súbita en el pecho',
                                'dificultad_respiratoria' => 'Dificultad respiratoria severa o sensación de asfixia',
                                'perdida_conciencia' => 'Pérdida de la conciencia, desmayo o desorientación extrema',
                                'hemorragia' => 'Hemorragia o sangrado abundante que no cesa',
                                'convulsiones' => 'Convulsiones recientes o parálisis/debilidad repentina en extremidades o cara',
                                'fiebre_alta' => 'Fiebre muy alta (>39°C) con rigidez en el cuello o en menores de edad',
                            ];
                        @endphp

                        @foreach ($alarmOptions as $key => $label)
                            <div class="col-md-6">
                                <div class="form-check p-3 border rounded bg-light">
                                    <input class="form-check-input alarm-sign-checkbox" type="checkbox" name="warning_signs[]" value="{{ $key }}" id="alarm_{{ $key }}"
                                        {{ is_array(old('warning_signs')) && in_array($key, old('warning_signs')) ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold text-danger" for="alarm_{{ $key }}">
                                        {{ $label }}
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- 2. Datos del Paciente --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">2. Datos del Paciente</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="full_name" class="form-label fw-semibold">Nombre Completo del Paciente <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name') }}" required minlength="2" maxlength="150" placeholder="Primer y segundo nombre, apellidos">
                            @error('full_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="document_type" class="form-label fw-semibold">Tipo de Documento</label>
                            <select class="form-select @error('document_type') is-invalid @enderror" id="document_type" name="document_type">
                                <option value="">-- Seleccionar (Opcional si no posee) --</option>
                                <option value="Cédula de ciudadanía" {{ old('document_type') === 'Cédula de ciudadanía' ? 'selected' : '' }}>Cédula de ciudadanía</option>
                                <option value="Tarjeta de identidad" {{ old('document_type') === 'Tarjeta de identidad' ? 'selected' : '' }}>Tarjeta de identidad (Menor)</option>
                                <option value="Registro civil" {{ old('document_type') === 'Registro civil' ? 'selected' : '' }}>Registro civil (Menor)</option>
                                <option value="Cédula de extranjería" {{ old('document_type') === 'Cédula de extranjería' ? 'selected' : '' }}>Cédula de extranjería</option>
                                <option value="Pasaporte" {{ old('document_type') === 'Pasaporte' ? 'selected' : '' }}>Pasaporte</option>
                                <option value="Permiso especial" {{ old('document_type') === 'Permiso especial' ? 'selected' : '' }}>Permiso especial de permanencia / PPT</option>
                                <option value="Menor sin identificación" {{ old('document_type') === 'Menor sin identificación' ? 'selected' : '' }}>Menor sin identificación</option>
                            </select>
                            @error('document_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="document_number" class="form-label fw-semibold" id="document_number_label">Número de Documento</label>
                            <input type="text" class="form-control @error('document_number') is-invalid @enderror" id="document_number" name="document_number" value="{{ old('document_number') }}" maxlength="20" placeholder="Número de identificación">
                            <small class="form-text text-muted" id="document_help_text">Obligatorio si seleccionó un tipo de documento distinto de 'Menor sin identificación'.</small>
                            @error('document_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="birth_date" class="form-label fw-semibold">Fecha de Nacimiento</label>
                            <input type="date" class="form-control @error('birth_date') is-invalid @enderror" id="birth_date" name="birth_date" value="{{ old('birth_date') }}" max="{{ date('Y-m-d') }}">
                            @error('birth_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="gender" class="form-label fw-semibold">Sexo / Género</label>
                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                                <option value="">-- No especificado --</option>
                                <option value="femenino" {{ old('gender') === 'femenino' ? 'selected' : '' }}>Femenino</option>
                                <option value="masculino" {{ old('gender') === 'masculino' ? 'selected' : '' }}>Masculino</option>
                                <option value="otro" {{ old('gender') === 'otro' ? 'selected' : '' }}>Otro</option>
                            </select>
                            @error('gender')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="patient_phone" class="form-label fw-semibold">Teléfono del Paciente</label>
                            <input type="tel" class="form-control @error('patient_phone') is-invalid @enderror" id="patient_phone" name="patient_phone" value="{{ old('patient_phone') }}" placeholder="Ej: 3101234567">
                            <small class="form-text text-muted">Requerido al menos un teléfono (paciente o acompañante).</small>
                            @error('patient_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Motivo de Urgencia e Información Clínica --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">3. Motivo de Urgencia e Información Clínica</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="emergency_reason" class="form-label fw-semibold">Motivo de Consulta de Urgencia <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('emergency_reason') is-invalid @enderror" id="emergency_reason" name="emergency_reason" rows="3" required maxlength="500" placeholder="Describa brevemente qué síntomas o dolencias presenta actualmente">{{ old('emergency_reason') }}</textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">Describa con claridad el motivo principal (máx. 500 caracteres).</small>
                                <small class="text-muted"><span id="reasonCharCount">0</span>/500</small>
                            </div>
                            @error('emergency_reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="blood_type" class="form-label fw-semibold">Grupo Sanguíneo y Factor RH</label>
                            <select class="form-select @error('blood_type') is-invalid @enderror" id="blood_type" name="blood_type">
                                <option value="No sé" {{ old('blood_type') === 'No sé' ? 'selected' : '' }}>No sé</option>
                                <option value="O+" {{ old('blood_type') === 'O+' ? 'selected' : '' }}>O+</option>
                                <option value="O-" {{ old('blood_type') === 'O-' ? 'selected' : '' }}>O-</option>
                                <option value="A+" {{ old('blood_type') === 'A+' ? 'selected' : '' }}>A+</option>
                                <option value="A-" {{ old('blood_type') === 'A-' ? 'selected' : '' }}>A-</option>
                                <option value="B+" {{ old('blood_type') === 'B+' ? 'selected' : '' }}>B+</option>
                                <option value="B-" {{ old('blood_type') === 'B-' ? 'selected' : '' }}>B-</option>
                                <option value="AB+" {{ old('blood_type') === 'AB+' ? 'selected' : '' }}>AB+</option>
                                <option value="AB-" {{ old('blood_type') === 'AB-' ? 'selected' : '' }}>AB-</option>
                            </select>
                            @error('blood_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">¿Tiene alergias a medicamentos? <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4 pt-1">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="medication_allergies" id="allergy_none" value="ninguna" {{ old('medication_allergies', 'ninguna') === 'ninguna' ? 'checked' : '' }} required>
                                    <label class="form-check-label" for="allergy_none">Ninguna conocida</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="medication_allergies" id="allergy_unknown" value="no_se" {{ old('medication_allergies') === 'no_se' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="allergy_unknown">No sé</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="medication_allergies" id="allergy_yes" value="si" {{ old('medication_allergies') === 'si' ? 'checked' : '' }}>
                                    <label class="form-check-label text-danger fw-bold" for="allergy_yes">Sí presenta alergias</label>
                                </div>
                            </div>
                            @error('medication_allergies')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12 {{ old('medication_allergies') === 'si' ? '' : 'd-none' }}" id="allergyDetailContainer">
                            <label for="allergies_detail" class="form-label fw-semibold text-danger">Detalle de Alergias a Medicamentos <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('allergies_detail') is-invalid @enderror" id="allergies_detail" name="allergies_detail" value="{{ old('allergies_detail') }}" maxlength="500" placeholder="Indique el nombre de los medicamentos a los cuales presenta reacción alérgica">
                            @error('allergies_detail')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <label for="additional_notes" class="form-label fw-semibold">Notas Adicionales / Antecedentes Relevantes</label>
                            <textarea class="form-control @error('additional_notes') is-invalid @enderror" id="additional_notes" name="additional_notes" rows="2" maxlength="1000" placeholder="Medicamentos actuales, enfermedades de base, cirugías previas, etc.">{{ old('additional_notes') }}</textarea>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">Información complementaria opcional (máx. 1000 caracteres).</small>
                                <small class="text-muted"><span id="notesCharCount">0</span>/1000</small>
                            </div>
                            @error('additional_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. Datos del Acompañante o Representante Legal --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-primary">4. Datos del Acompañante o Representante Legal</h5>
                    <span class="badge bg-secondary" id="companionStatusBadge">Opcional para adultos</span>
                </div>
                <div class="card-body p-4">
                    <div id="minorNotice" class="alert alert-info py-2 mb-3 d-none">
                        ℹ️ <strong>Paciente menor de edad:</strong> Los datos del acompañante o representante legal son obligatorios por disposición legal.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="companion_name" class="form-label fw-semibold">Nombre Completo del Acompañante</label>
                            <input type="text" class="form-control @error('companion_name') is-invalid @enderror" id="companion_name" name="companion_name" value="{{ old('companion_name') }}" maxlength="150" placeholder="Nombre y apellidos">
                            @error('companion_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="companion_phone" class="form-label fw-semibold">Teléfono del Acompañante</label>
                            <input type="tel" class="form-control @error('companion_phone') is-invalid @enderror" id="companion_phone" name="companion_phone" value="{{ old('companion_phone') }}" placeholder="Ej: 3209876543">
                            @error('companion_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="companion_relationship" class="form-label fw-semibold">Parentesco o Relación</label>
                            <input type="text" class="form-control @error('companion_relationship') is-invalid @enderror" id="companion_relationship" name="companion_relationship" value="{{ old('companion_relationship') }}" maxlength="50" placeholder="Ej: Madre, Padre, Cónyuge, Hijo/a, Tutor/a">
                            @error('companion_relationship')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- 5. Consentimientos de Ley 1581 de 2012 --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">5. Consentimientos y Autorizaciones Legales</h5>
                </div>
                <div class="card-body p-4">

                    {{-- Consentimiento Obligatorio Ley 1581 --}}
                    <div class="form-check p-3 border rounded mb-3 bg-light @error('consent_data_treatment') border-danger @enderror">
                        <input class="form-check-input mt-1" type="checkbox" name="consent_data_treatment" id="consent_data_treatment" value="1" {{ old('consent_data_treatment') ? 'checked' : '' }} required>
                        <label class="form-check-label ms-2" for="consent_data_treatment">
                            <span class="fw-bold text-dark">Autorización para el Tratamiento de Datos Personales y Sensibles (Obligatorio)</span> <span class="text-danger">*</span>
                            <p class="small text-muted mb-1 mt-1">
                                En cumplimiento de la <strong>Ley Estatutaria 1581 de 2012</strong>, autorizo de manera voluntaria, previa, explícita e informada al <strong>Hospital El Salvador de Ubaté E.S.E.</strong> para tratar mis datos personales y de salud con la exclusiva finalidad de gestionar mi pre-registro y atención médica de urgencias.
                            </p>
                            <p class="small text-muted mb-0">
                                <em>Se informa expresamente el carácter facultativo de las respuestas sobre datos sensibles de salud y los derechos del titular a conocer, actualizar, rectificar y revocar ante el canal institucional: protecciondedatos@hospitalubate.gov.co.</em>
                            </p>
                        </label>
                        @error('consent_data_treatment')
                            <div class="text-danger small mt-2 fw-semibold">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Consentimiento Opcional Separado para el Acompañante --}}
                    <div class="form-check p-3 border rounded">
                        <input class="form-check-input mt-1" type="checkbox" name="consent_share_companion" id="consent_share_companion" value="1" {{ old('consent_share_companion') ? 'checked' : '' }}>
                        <label class="form-check-label ms-2" for="consent_share_companion">
                            <span class="fw-bold text-dark">Autorización para Compartir Estado con el Acompañante (Opcional)</span>
                            <p class="small text-muted mb-0 mt-1">
                                Autorizo expresamente a la institución a compartir información sobre el estado general y ubicación de mi atención de urgencia con el acompañante o representante registrado. Esta autorización es revocable en cualquier momento.
                            </p>
                        </label>
                    </div>

                </div>
            </div>

            {{-- Botón de Envío --}}
            <div class="d-grid gap-2 mb-5">
                <button type="submit" class="btn btn-primary btn-lg shadow fw-bold py-3">
                    🏥 Enviar Pre-registro de Urgencia
                </button>
                <div class="text-center text-muted small mt-2">
                    Sus datos viajan protegidos bajo cifrado de alto nivel y estrictos protocolos de confidencialidad médica.
                </div>
            </div>

        </form>

    </div>
</div>
@endsection
