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
                    <small class="text-muted">Acceso Excepcional Requerido - Ley 1581 de 2012 / Res. 1995 de 1999</small>
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

                {{-- Errores de validación o restricción de turno --}}
                @if ($errors->any())
                    <div class="alert alert-danger mb-4" role="alert">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (empty($hasActiveShift))
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-slash-circle-fill fs-4 me-3 flex-shrink-0" aria-hidden="true"></i>
                        <div>
                            <strong>Acceso denegado por política de seguridad:</strong>
                            No cuenta con un turno asistencial activo registrado. La apertura de historia clínica bajo causal excepcional requiere encontrarse en servicio activo en el hospital.
                        </div>
                    </div>
                @endif

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

                @if (! empty($hasActiveShift))
                <form action="{{ route('episodes.break_glass.store', $episode) }}" method="POST" id="breakGlassForm">
                    @csrf

                    <div class="mb-3">
                        <label for="category" class="form-label fw-bold text-dark">
                            Causal de Acceso Excepcional <span class="text-danger">*</span>
                        </label>
                        <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                            <option value="">-- Seleccione la causal clínico-asistencial --</option>
                            <option value="emergencia_vital" {{ old('category') === 'emergencia_vital' ? 'selected' : '' }}>Emergencia Vital / Reanimación / Código Azul</option>
                            <option value="interconsulta_urgente" {{ old('category') === 'interconsulta_urgente' ? 'selected' : '' }}>Interconsulta Médica Urgente</option>
                            <option value="relevo_turno" {{ old('category') === 'relevo_turno' ? 'selected' : '' }}>Relevo o Reemplazo Imprevisto de Turno</option>
                            <option value="ausencia_tratante" {{ old('category') === 'ausencia_tratante' ? 'selected' : '' }}>Ausencia Temporal del Médico Tratante</option>
                            <option value="otro" {{ old('category') === 'otro' ? 'selected' : '' }}>Otro Motivo Asistencial Justificado</option>
                        </select>
                        @error('category')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="exceptional_reason" class="form-label fw-bold text-dark">
                            Justificación Clínica Detallada <span class="text-danger">*</span>
                        </label>
                        <textarea
                            name="exceptional_reason"
                            id="exceptional_reason"
                            rows="3"
                            class="form-control @error('exceptional_reason') is-invalid @enderror"
                            placeholder="Describa de forma precisa la necesidad asistencial o médica por la cual requiere consultar este expediente (mínimo 15 caracteres)..."
                            minlength="15"
                            maxlength="500"
                            required
                        >{{ old('exceptional_reason') }}</textarea>
                        <div class="form-text small">
                            Mínimo 15 caracteres. Acceso temporal por 30 minutos; se genera alerta forense inmediata para coordinación médica y auditoría.
                        </div>
                        @error('exceptional_reason')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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
                @else
                <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                    <a href="{{ route('episodes.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Regresar al listado de urgencias
                    </a>
                </div>
                @endif
            </div>
            <div class="card-footer bg-light py-2 px-4 text-muted small text-center">
                Hospital de Ubaté - Sistema Seguro de Urgencias. Todo intento de acceso es trazable y peritable.
            </div>
        </div>
    </div>
</div>
@endsection
