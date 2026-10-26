@extends('layouts.app')

@section('title', 'Control de Acceso Clínico - Hospital de Ubaté')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-md-8 col-lg-7">
        <div class="card shadow-sm border border-warning">
            <div class="card-header bg-warning-subtle text-warning-emphasis py-3 px-4 d-flex align-items-center">
                <i class="bi bi-shield-exclamation fs-3 me-3 text-warning-emphasis" aria-hidden="true"></i>
                <div>
                    <h1 class="h5 mb-0 fw-bold">Control de Custodia de Historia Clínica</h1>
                    <small class="text-muted">Acceso Excepcional Requerido — Ley 1581 de 2012 / Res. 1995 de 1999</small>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0" aria-hidden="true"></i>
                    <div class="small">
                        <strong>ADVERTENCIA MÉDICO-LEGAL:</strong>
                        Usted no se encuentra registrado como médico responsable ni personal de apoyo asignado a la atención de este paciente.
                        La historia clínica es un documento privado sujeto a reserva legal.
                        Para consultar este expediente asistencial, debe declarar y justificar la causal clínica o institucional.
                        <strong>Este acceso quedará registrado de forma inmutable en la bitácora de auditoría forense del hospital.</strong>
                    </div>
                </div>

                {{-- Datos mínimos de verificación del paciente --}}
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="row g-2 small">
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Código de Episodio:</span>
                            <span class="fw-bold font-monospace tabular-nums">{{ $episode->public_code }}</span>
                            @if ($episode->is_nn)
                                <span class="badge bg-danger ms-1">NN: {{ $episode->nn_code }}</span>
                            @endif
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Paciente:</span>
                            <span class="fw-bold">{{ $episode->patient?->full_name ?? 'Paciente no identificado' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Médico Tratante Asignado:</span>
                            <span class="fw-semibold text-primary">
                                {{ $episode->responsibleUser?->name ?? 'Sin médico asignado actualmente' }}
                            </span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block">Ubicación Actual:</span>
                            <span>{{ $episode->activeBedAssignment?->bed?->code ?? 'Pendiente de camilla' }}</span>
                        </div>
                    </div>
                </div>

                <form action="{{ route('episodes.show', $episode) }}" method="GET" id="breakGlassForm">
                    <div class="mb-3">
                        <label for="exceptional_reason" class="form-label fw-bold text-dark">
                            Motivo justificado del acceso excepcional <span class="text-danger">*</span>
                        </label>
                        <textarea
                            name="exceptional_reason"
                            id="exceptional_reason"
                            rows="3"
                            class="form-control"
                            placeholder="Describa el motivo clínico asistencial o de urgencia por el cual requiere consultar esta historia clínica (mínimo 10 caracteres)..."
                            minlength="10"
                            maxlength="500"
                            required
                        >{{ old('exceptional_reason') }}</textarea>
                        <div class="form-text small">
                            Mínimo 10 caracteres. Sea preciso: este texto formará parte del expediente de auditoría médica.
                        </div>
                    </div>

                    {{-- Opciones rápidas de justificación de urgencia --}}
                    <div class="mb-4">
                        <span class="small text-muted d-block mb-1">Causales habituales de urgencias (clic para seleccionar):</span>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" onclick="setReason('Relevo o reemplazo imprevisto de turno de urgencias')">
                                Relevo de turno
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" onclick="setReason('Interconsulta médica o segunda opinión de urgencia')">
                                Interconsulta médica
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" onclick="setReason('Código azul / Paciente descompensado en sala de urgencias')">
                                Código azul / Descompensación
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" onclick="setReason('Atención médica requerida por ausencia temporal del médico tratante')">
                                Ausencia temporal de tratante
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center gap-2 pt-2 border-top">
                        <a href="{{ route('episodes.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Cancelar y volver
                        </a>
                        <button type="submit" class="btn btn-warning text-dark fw-bold px-4">
                            <i class="bi bi-shield-check me-1" aria-hidden="true"></i> Confirmar Acceso y Auditar
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light py-2 px-4 text-muted small text-center">
                Hospital de Ubaté — Sistema Seguro de Urgencias. Todo intento de acceso es trazable y peritable.
            </div>
        </div>
    </div>
</div>

<script>
function setReason(text) {
    var textarea = document.getElementById('exceptional_reason');
    if (textarea) {
        textarea.value = text;
        textarea.focus();
    }
}
</script>
@endsection
