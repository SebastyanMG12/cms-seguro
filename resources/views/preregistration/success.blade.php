@extends('layouts.app')

@section('title', 'Pre-registro Confirmado - Hospital de Ubaté')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">

        {{-- Tarjeta Principal de Éxito --}}
        <div class="card shadow-sm border-0 mb-4 text-center">
            <div class="card-body p-5">
                <div class="mb-3">
                    <i class="bi bi-check-circle text-success display-4" aria-hidden="true"></i>
                </div>
                <h1 class="h2 fw-bold text-dark mb-2">Pre-registro Registrado Exitosamente</h1>
                <p class="text-muted fs-5 mb-4">
                    Su solicitud ha sido ingresada en el sistema de recepción del servicio de urgencias del Hospital de Ubaté.
                </p>

                {{-- Código Público Destacado --}}
                <div class="bg-light p-4 rounded-3 border mb-4">
                    <span class="text-uppercase text-muted fw-bold small d-block mb-1">Su Código de Atención de Urgencia</span>
                    <span class="display-4 fw-bold text-primary font-monospace tabular-nums" id="preregistrationPublicCode">{{ $publicCode }}</span>
                    <div class="mt-2 text-muted small">
                        Guarde o tome una captura de pantalla de este código para presentarlo en ventanilla.
                    </div>
                </div>

                <div class="alert alert-info border-info small text-start mb-4">
                    <i class="bi bi-clock-history me-1" aria-hidden="true"></i> <strong>Vigencia:</strong> Este código de pre-registro es válido hasta el <strong>{{ $expiresAt?->format('d/m/Y H:i') ?? 'próximas 24 horas' }}</strong>. Si no se presenta dentro de este período, el registro expirará automáticamente por políticas de seguridad clínica.
                </div>

                {{-- Pasos al llegar a la institución --}}
                <div class="text-start border-top pt-4">
                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-card-checklist me-2" aria-hidden="true"></i>Pasos a seguir al presentarse en el hospital:</h5>
                    <ol class="list-group list-group-numbered mb-4">
                        <li class="list-group-item border-0 ps-0">
                            <strong>Presente su documento y este código:</strong> Acérquese a la ventanilla de Admisiones / Triage del Servicio de Urgencias e indique su código <code>{{ $publicCode }}</code>.
                        </li>
                        <li class="list-group-item border-0 ps-0">
                            <strong>Valoración médica de Triage:</strong> Un profesional de enfermería o médico realizará la toma de signos vitales y asignará el nivel de prioridad clínica (Resolución 5596 de 2015).
                        </li>
                        <li class="list-group-item border-0 ps-0">
                            <strong>Llamado a consulta o reanimación:</strong> Será llamado según su nivel de triage asignado.
                        </li>
                    </ol>
                </div>

                {{-- Recordatorio de signos de alarma --}}
                <div class="alert alert-warning border-warning small text-start mb-4">
                    <i class="bi bi-exclamation-triangle text-dark me-1" aria-hidden="true"></i> <strong>Recuerde:</strong> Si en el trayecto hacia el hospital sus síntomas empeoran drásticamente (dificultad respiratoria severa, dolor de pecho, convulsiones o desmayo), notifíquelo de inmediato al ingresar al área de urgencias para activación del protocolo de emergencia.
                </div>

                <div class="d-flex justify-content-center gap-3">
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary px-4">
                        Volver al Inicio
                    </a>
                    <button type="button" class="btn btn-primary px-4" id="printCodeBtn">
                        <i class="bi bi-printer me-2" aria-hidden="true"></i> Imprimir / Guardar Comprobante
                    </button>
                </div>

            </div>
        </div>

    </div>
</div>
@endsection
