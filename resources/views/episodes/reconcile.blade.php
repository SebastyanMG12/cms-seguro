@extends('layouts.app')

@section('title', 'Conciliación de Identidad: ' . $episode->nn_code)

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-xl-11">
        {{-- Navegación superior --}}
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2 small">
                <li class="breadcrumb-item"><a href="{{ route('episodes.index') }}" class="text-decoration-none">Urgencias</a></li>
                <li class="breadcrumb-item"><a href="{{ route('episodes.show', $episode) }}" class="text-decoration-none">Episodio {{ $episode->public_code }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Conciliación de Identidad</li>
            </ol>
        </nav>

        {{-- Encabezado --}}
        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
            <div>
                <h1 class="h4 mb-0 fw-bold text-dark">
                    <i class="bi bi-person-check text-primary me-2" aria-hidden="true"></i>Conciliación de Identidad de Paciente NN
                </h1>
                <p class="text-muted small mb-0">Vinculación de historia clínica de emergencia con la identidad civil verificada del paciente.</p>
            </div>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 fs-6 font-monospace tabular-nums">
                <i class="bi bi-tag me-1" aria-hidden="true"></i>{{ $episode->nn_code }}
            </span>
        </div>

        {{-- Alertas de validación --}}
        @if ($errors->any())
            <div class="alert alert-danger py-2 px-3 small mb-3" role="alert">
                <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>Corrija los siguientes errores:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Vista comparativa en dos columnas (Comparison View) --}}
        <div class="row g-3">
            {{-- Columna Izquierda: Datos del Registro NN Original (Solo Lectura) --}}
            <div class="col-12 col-lg-5">
                <div class="card border shadow-sm h-100">
                    <div class="card-header bg-light py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                        <strong class="small text-dark">
                            <i class="bi bi-file-earmark-person me-1 text-danger" aria-hidden="true"></i>Registro de Ingreso Original (NN)
                        </strong>
                        <span class="badge bg-danger small">Inmutable</span>
                    </div>
                    <div class="card-body p-3 small">
                        <div class="mb-3 p-2 bg-light rounded border">
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="text-muted d-block">Identificador NN:</span>
                                    <strong class="font-monospace tabular-nums text-dark">{{ $episode->nn_code }}</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block">Código Episodio:</span>
                                    <strong class="font-monospace tabular-nums text-dark">{{ $episode->public_code }}</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block">Fecha y Hora Llegada:</span>
                                    <span class="tabular-nums text-dark">{{ $episode->arrived_at?->format('d/m/Y H:i') ?? 'N/D' }}</span>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block">Sexo preliminar:</span>
                                    <span class="text-capitalize text-dark">{{ $episode->apparent_sex }}</span>
                                </div>
                                <div class="col-12">
                                    <span class="text-muted d-block">Edad aproximada:</span>
                                    <span class="text-dark">{{ $episode->estimated_age }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="text-muted fw-semibold d-block mb-1">Medio de Llegada y Remitente:</span>
                            <div class="text-dark">
                                <i class="bi bi-truck me-1 text-secondary" aria-hidden="true"></i>{{ ucfirst($episode->arrival_mode) }} &mdash; 
                                {{ $episode->brought_by_name }}
                                @if ($episode->brought_by_contact)
                                    <span class="text-muted">({{ $episode->brought_by_contact }})</span>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="text-muted fw-semibold d-block mb-1">Señas Particulares y Vestimenta al Ingreso:</span>
                            <div class="p-2 bg-light border rounded text-dark text-wrap">
                                {{ $episode->distinguishing_marks }}
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="text-muted fw-semibold d-block mb-1">Motivo Aparente de Urgencia:</span>
                            <div class="p-2 bg-light border rounded text-dark">
                                {{ $episode->reason }}
                            </div>
                        </div>

                        <div class="alert alert-info py-2 px-2 small mb-0 border-info">
                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                            El registro inicial NN no se eliminará; se conservará para fines forenses, legales y de auditoría inmutable.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Columna Derecha: Formulario de Conciliación con Identidad Verificada --}}
            <div class="col-12 col-lg-7">
                <div class="card border shadow-sm">
                    <div class="card-header bg-light py-2 px-3 border-bottom">
                        <strong class="small text-dark">
                            <i class="bi bi-person-vcard me-1 text-primary" aria-hidden="true"></i>Identidad Verificada del Paciente
                        </strong>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <form method="POST" action="{{ route('episodes.reconcile.store', $episode) }}" id="reconcileForm" novalidate>
                            @csrf

                            {{-- Selección de Modalidad --}}
                            <div class="mb-3">
                                <label class="form-label small fw-semibold d-block mb-2">Modalidad de Conciliación</label>
                                <div class="btn-group w-100" role="group" aria-label="Modalidad de conciliación">
                                    <input type="radio" class="btn-check" name="reconciliation_mode" id="mode_new" value="new" {{ old('reconciliation_mode', 'new') === 'new' ? 'checked' : '' }} autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm py-2" for="mode_new">
                                        <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Registrar Identidad Verificada
                                    </label>

                                    <input type="radio" class="btn-check" name="reconciliation_mode" id="mode_existing" value="existing" {{ old('reconciliation_mode') === 'existing' ? 'checked' : '' }} autocomplete="off">
                                    <label class="btn btn-outline-primary btn-sm py-2" for="mode_existing">
                                        <i class="bi bi-search me-1" aria-hidden="true"></i>Vincular a Paciente Existente en BD
                                    </label>
                                </div>
                            </div>

                            {{-- Sección: Registrar Nueva Identidad Verificada --}}
                            <div id="sectionNewPatient" class="{{ old('reconciliation_mode', 'new') === 'new' ? '' : 'd-none' }}">
                                <div class="row g-2 mb-2">
                                    <div class="col-12 col-md-5">
                                        <label for="document_type" class="form-label small fw-semibold">Tipo de Documento <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="document_type" name="document_type">
                                            <option value="">Seleccione tipo...</option>
                                            <option value="Cédula de ciudadanía" {{ old('document_type') === 'Cédula de ciudadanía' ? 'selected' : '' }}>Cédula de ciudadanía (CC)</option>
                                            <option value="Tarjeta de identidad" {{ old('document_type') === 'Tarjeta de identidad' ? 'selected' : '' }}>Tarjeta de identidad (TI)</option>
                                            <option value="Cédula de extranjería" {{ old('document_type') === 'Cédula de extranjería' ? 'selected' : '' }}>Cédula de extranjería (CE)</option>
                                            <option value="Pasaporte" {{ old('document_type') === 'Pasaporte' ? 'selected' : '' }}>Pasaporte</option>
                                            <option value="Permiso por protección temporal" {{ old('document_type') === 'Permiso por protección temporal' ? 'selected' : '' }}>Permiso protección temporal (PPT)</option>
                                            <option value="Registro civil" {{ old('document_type') === 'Registro civil' ? 'selected' : '' }}>Registro civil</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-7">
                                        <label for="document_number" class="form-label small fw-semibold">Número de Documento <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm tabular-nums" id="document_number" name="document_number" value="{{ old('document_number') }}" placeholder="Número sin puntos ni espacios" maxlength="30" autocomplete="off">
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label for="full_name" class="form-label small fw-semibold">Nombre Completo Verificado <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="full_name" name="full_name" value="{{ old('full_name') }}" placeholder="Nombres y apellidos completos según documento" maxlength="150" autocomplete="off">
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-12 col-md-4">
                                        <label for="birth_date" class="form-label small fw-semibold">Fecha de Nacimiento</label>
                                        <input type="date" class="form-control form-control-sm tabular-nums" id="birth_date" name="birth_date" value="{{ old('birth_date') }}" max="{{ date('Y-m-d') }}">
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label for="gender" class="form-label small fw-semibold">Sexo / Género</label>
                                        <select class="form-select form-select-sm" id="gender" name="gender">
                                            <option value="masculino" {{ old('gender', $episode->apparent_sex) === 'masculino' ? 'selected' : '' }}>Masculino</option>
                                            <option value="femenino" {{ old('gender', $episode->apparent_sex) === 'femenino' ? 'selected' : '' }}>Femenino</option>
                                            <option value="indeterminado" {{ old('gender', $episode->apparent_sex) === 'indeterminado' ? 'selected' : '' }}>Indeterminado</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label for="phone" class="form-label small fw-semibold">Teléfono de Contacto</label>
                                        <input type="tel" class="form-control form-control-sm tabular-nums" id="phone" name="phone" value="{{ old('phone') }}" placeholder="3XXXXXXXXX" maxlength="20" autocomplete="off">
                                    </div>
                                </div>
                            </div>

                            {{-- Sección: Buscar y Vincular Paciente Existente --}}
                            <div id="sectionExistingPatient" class="{{ old('reconciliation_mode') === 'existing' ? '' : 'd-none' }}">
                                <div class="p-3 bg-light rounded border mb-3">
                                    <label class="form-label small fw-semibold mb-1">Buscar Paciente en Base de Datos Hospitalaria</label>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-5">
                                            <select class="form-select form-select-sm" id="search_doc_type">
                                                <option value="Cédula de ciudadanía">Cédula de ciudadanía</option>
                                                <option value="Tarjeta de identidad">Tarjeta de identidad</option>
                                                <option value="Cédula de extranjería">Cédula de extranjería</option>
                                                <option value="Pasaporte">Pasaporte</option>
                                                <option value="Permiso por protección temporal">PPT</option>
                                            </select>
                                        </div>
                                        <div class="col-5">
                                            <input type="text" class="form-control form-control-sm tabular-nums" id="search_doc_number" placeholder="Número de documento">
                                        </div>
                                        <div class="col-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btnSearchPatient">
                                                <i class="bi bi-search" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div id="searchResultArea" class="mt-2 d-none">
                                        <div class="alert alert-success py-2 px-3 small mb-0 border-success d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong id="foundPatientName" class="d-block text-dark"></strong>
                                                <span id="foundPatientDoc" class="text-muted tabular-nums"></span>
                                            </div>
                                            <input type="hidden" name="existing_patient_id" id="existing_patient_id" value="{{ old('existing_patient_id') }}">
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Seleccionado</span>
                                        </div>
                                    </div>

                                    <div id="searchNotFoundArea" class="mt-2 d-none">
                                        <div class="alert alert-secondary py-1 px-2 small mb-0">
                                            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>No se encontró paciente con ese documento. Seleccione la opción "Registrar Identidad Verificada".
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Campo Obligatorio de Evidencia de Identificación --}}
                            <div class="mb-3 pt-2 border-top">
                                <label for="reconciliation_evidence" class="form-label small fw-semibold">
                                    Evidencia de Verificación de Identidad <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control form-control-sm @error('reconciliation_evidence') is-invalid @enderror" id="reconciliation_evidence" name="reconciliation_evidence" rows="3" placeholder="Detalle la evidencia documental, pericial o presencial que respalda la conciliación (Ej: Cédula física original aportada por la cónyuge Sra. Carmen Gómez, verificación dactilar por dactiloscopista de SIJIN, carné institucional verificado, etc.)." required minlength="10" maxlength="2000">{{ old('reconciliation_evidence') }}</textarea>
                                @error('reconciliation_evidence')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text small">Obligatorio por trazabilidad de custodia y auditoría legal (mínimo 10 caracteres).</div>
                            </div>

                            {{-- Aviso Legal y Confirmación --}}
                            <div class="alert alert-warning py-2 px-3 small mb-3 border-warning">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-shield-lock-fill text-warning me-2" aria-hidden="true"></i>
                                    <div>
                                        Esta acción vinculará formalmente la historia clínica de urgencia con el registro del paciente identificado y quedará registrada en la bitácora inmutable de auditoría con su usuario y marca de tiempo.
                                    </div>
                                </div>
                            </div>

                            {{-- Botones de Acción --}}
                            <div class="d-flex justify-content-between align-items-center pt-2">
                                <a href="{{ route('episodes.show', $episode) }}" class="btn btn-outline-secondary btn-sm px-3">
                                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Regresar
                                </a>
                                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold shadow-sm" onclick="return confirm('¿Confirma la conciliación de identidad de este paciente NN con la evidencia ingresada?');">
                                    <i class="bi bi-patch-check-fill me-1" aria-hidden="true"></i>Confirmar Conciliación de Identidad
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modeNew = document.getElementById('mode_new');
    const modeExisting = document.getElementById('mode_existing');
    const sectionNew = document.getElementById('sectionNewPatient');
    const sectionExisting = document.getElementById('sectionExistingPatient');

    function toggleMode() {
        if (modeNew && modeNew.checked) {
            sectionNew.classList.remove('d-none');
            sectionExisting.classList.add('d-none');
        } else if (modeExisting && modeExisting.checked) {
            sectionNew.classList.add('d-none');
            sectionExisting.classList.remove('d-none');
        }
    }

    if (modeNew) modeNew.addEventListener('change', toggleMode);
    if (modeExisting) modeExisting.addEventListener('change', toggleMode);

    // Búsqueda AJAX de paciente existente
    const btnSearch = document.getElementById('btnSearchPatient');
    if (btnSearch) {
        btnSearch.addEventListener('click', function () {
            const docType = document.getElementById('search_doc_type').value;
            const docNumber = document.getElementById('search_doc_number').value.trim();
            const resultArea = document.getElementById('searchResultArea');
            const notFoundArea = document.getElementById('searchNotFoundArea');
            const hiddenId = document.getElementById('existing_patient_id');

            if (!docNumber) {
                alert('Ingrese un número de documento para buscar.');
                return;
            }

            fetch(`{{ route('episodes.patient.search') }}?document_type=${encodeURIComponent(docType)}&document_number=${encodeURIComponent(docNumber)}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (data.found && data.patient) {
                    document.getElementById('foundPatientName').textContent = data.patient.full_name;
                    document.getElementById('foundPatientDoc').textContent = `${data.patient.document_type}: ${data.patient.document_number}`;
                    hiddenId.value = data.patient.id;
                    resultArea.classList.remove('d-none');
                    notFoundArea.classList.add('d-none');
                } else {
                    hiddenId.value = '';
                    resultArea.classList.add('d-none');
                    notFoundArea.classList.remove('d-none');
                }
            })
            .catch(() => {
                alert('Error al consultar el paciente.');
            });
        });
    }
});
</script>
@endsection
