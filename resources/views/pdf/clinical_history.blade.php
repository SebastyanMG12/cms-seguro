<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historia Clínica - {{ $episode->public_code }} - Hospital de Ubaté</title>
    <style>
        @page {
            margin: 28mm 12mm 18mm 12mm;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            line-height: 1.35;
            color: #1a1a1a;
            background-color: #ffffff;
        }

        /* Marca de agua forense diagonal en cada página */
        .watermark {
            position: fixed;
            top: 35%;
            left: -15%;
            width: 130%;
            text-align: center;
            opacity: 0.07;
            transform: rotate(-35deg);
            transform-origin: 50% 50%;
            font-size: 22pt;
            font-weight: bold;
            color: #000000;
            line-height: 1.4;
            z-index: -1000;
            text-transform: uppercase;
        }

        /* Encabezado fijo superior */
        header {
            position: fixed;
            top: -22mm;
            left: 0;
            right: 0;
            height: 18mm;
            border-bottom: 2px solid #0d5c9c;
            padding-bottom: 3mm;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-title {
            font-size: 11pt;
            font-weight: bold;
            color: #094067;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .header-subtitle {
            font-size: 7.5pt;
            color: #555555;
            margin: 1px 0 0 0;
        }

        .header-code {
            text-align: right;
            font-size: 8pt;
            color: #333333;
        }

        .header-code strong {
            font-size: 10.5pt;
            font-family: monospace;
            color: #0d5c9c;
        }

        /* Pie de página fijo inferior */
        footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            height: 8mm;
            border-top: 1px solid #cccccc;
            font-size: 7pt;
            color: #666666;
            line-height: 1.2;
            padding-top: 2mm;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .page-number:after {
            content: counter(page);
        }

        /* Secciones y contenedores */
        .section-box {
            border: 1px solid #cccccc;
            border-radius: 3px;
            margin-bottom: 3.5mm;
            background-color: #ffffff;
            page-break-inside: avoid;
        }

        .section-header {
            background-color: #f0f4f8;
            border-bottom: 1px solid #cccccc;
            padding: 1.8mm 3mm;
            font-size: 8.5pt;
            font-weight: bold;
            color: #094067;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .section-body {
            padding: 2.5mm 3mm;
        }

        /* Tablas de datos clave-valor */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
        }

        .data-table td {
            padding: 1.5mm 2mm;
            vertical-align: top;
        }

        .data-label {
            color: #555555;
            font-weight: normal;
            width: 25%;
        }

        .data-value {
            color: #111111;
            font-weight: bold;
            width: 25%;
        }

        .font-mono {
            font-family: monospace;
            font-size: 8.5pt;
        }

        /* Triage Badges */
        .triage-badge {
            display: inline-block;
            padding: 1mm 2.5mm;
            border-radius: 2px;
            font-weight: bold;
            font-size: 8pt;
            text-transform: uppercase;
        }
        .triage-1 { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .triage-2 { background-color: #ffe5d0; color: #7a3000; border: 1px solid #fed7aa; }
        .triage-3 { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
        .triage-4 { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .triage-5 { background-color: #cff4fc; color: #055160; border: 1px solid #b6effb; }

        /* Entradas clínicas */
        .clinical-entry {
            border: 1px solid #d9d9d9;
            border-radius: 2px;
            margin-bottom: 3mm;
            page-break-inside: avoid;
        }

        .clinical-entry-header {
            background-color: #f7f9fa;
            border-bottom: 1px solid #e1e4e8;
            padding: 1.5mm 2.5mm;
            font-size: 7.8pt;
        }

        .clinical-entry-type {
            font-weight: bold;
            text-transform: uppercase;
            color: #0d5c9c;
        }

        .clinical-entry-body {
            padding: 2.5mm;
            font-size: 8.2pt;
            white-space: pre-wrap;
            line-height: 1.35;
        }

        .clarification-box {
            margin: 1.5mm 2mm 2mm 2mm;
            padding: 1.5mm 2.5mm;
            background-color: #fffbf0;
            border-left: 3px solid #ffc107;
            font-size: 7.8pt;
        }

        .voided-box {
            background-color: #fff5f5;
            border-left: 3px solid #dc3545;
            padding: 1.5mm 2.5mm;
            margin-bottom: 2mm;
            font-size: 7.8pt;
            color: #842029;
        }

        /* Banner de Paciente NN */
        .nn-banner {
            background-color: #fff3cd;
            border: 1px solid #ffe69c;
            color: #664d03;
            padding: 2mm 3mm;
            margin-bottom: 3mm;
            border-radius: 2px;
            font-size: 8pt;
        }

        .text-muted { color: #6c757d; }
        .text-success { color: #198754; }
        .text-danger { color: #dc3545; }
        .text-primary { color: #0d5c9c; }
        .fw-bold { font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>

    {{-- Marca de agua de seguridad forense --}}
    <div class="watermark">
        {{ $watermarkText }}<br>
        COPIA OFICIAL PARA CUSTODIA LEGAL - RES. 1995/1999
    </div>

    {{-- Encabezado Institucional --}}
    <header>
        <table class="header-table">
            <tr>
                <td style="width: 70%;">
                    <div class="header-title">Hospital de Ubaté</div>
                    <div class="header-subtitle">
                        Departamento de Cundinamarca - Servicio de Atención de Urgencias<br>
                        Historia Clínica Electrónica Oficial - Resolución 1995 de 1999 / Ley 1581 de 2012
                    </div>
                </td>
                <td class="header-code" style="width: 30%;">
                    <div>EPISODIO CLÍNICO</div>
                    <strong class="font-mono">{{ $episode->public_code }}</strong>
                    <div style="font-size: 7pt; color: #666; margin-top: 1px;">
                        Estado: <strong>{{ strtoupper(str_replace('_', ' ', $episode->status)) }}</strong>
                    </div>
                </td>
            </tr>
        </table>
    </header>

    {{-- Pie de página legal con contador de página --}}
    <footer>
        <table class="footer-table">
            <tr>
                <td style="width: 65%;">
                    Hospital de Ubaté | Expediente: <span class="font-mono">{{ $episode->public_code }}</span> | Emitido: {{ $emissionTime->format('d/m/Y H:i:s') }} por {{ $staffUser->name }} ({{ $staffUser->roles->pluck('name')->implode(', ') }})
                </td>
                <td style="width: 35%; text-align: right;">
                    Documento Oficial Inmutable | Página <span class="page-number"></span>
                </td>
            </tr>
        </table>
    </footer>

    {{-- Contenido Principal --}}

    {{-- Alerta NN si aplica --}}
    @if ($episode->is_nn)
        <div class="nn-banner">
            @if ($episode->isReconciled())
                <strong>IDENTIDAD CONCILIADA Y VERIFICADA:</strong> Ingresó como <strong>{{ $episode->nn_code }}</strong>. Conciliado formalmente a <strong>{{ $episode->patient?->full_name }}</strong> el {{ $episode->reconciled_at?->format('d/m/Y H:i') }} por {{ $episode->reconciledByUser?->name }}.<br>
                <em>Evidencia: {{ $episode->reconciliation_evidence }}</em>
            @else
                <strong>PACIENTE NO IDENTIFICADO (NN):</strong> Identificador temporal: <strong>{{ $episode->nn_code }}</strong>. Atención de urgencias por interés vital (Ley 1581/2012 Art. 10). Identidad civil pendiente de verificación legal.
            @endif
        </div>
    @endif

    {{-- 1. Identificación del Paciente --}}
    <div class="section-box">
        <div class="section-header">1. Identificación del Paciente</div>
        <div class="section-body">
            <table class="data-table">
                <tr>
                    <td class="data-label">Nombre Completo:</td>
                    <td class="data-value" colspan="3">
                        @if ($episode->is_nn && ! $episode->isReconciled())
                            <span class="text-danger">PACIENTE NO IDENTIFICADO ({{ $episode->nn_code }})</span>
                        @else
                            {{ $episode->patient?->full_name ?? 'No registrado' }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="data-label">Tipo de Documento:</td>
                    <td class="data-value">
                        @if ($episode->is_nn && ! $episode->isReconciled())
                            Identificador NN
                        @else
                            {{ $episode->patient?->document_type ?? 'No registrado' }}
                        @endif
                    </td>
                    <td class="data-label">Número de Documento:</td>
                    <td class="data-value font-mono">
                        @if ($episode->is_nn && ! $episode->isReconciled())
                            {{ $episode->nn_code }}
                        @else
                            {{ $episode->patient?->document_number ?? 'No registrado' }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="data-label">Edad:</td>
                    <td class="data-value">{{ $patientAge ?? 'No especificada' }}</td>
                    <td class="data-label">Sexo:</td>
                    <td class="data-value">
                        @if ($episode->is_nn)
                            {{ ucfirst($episode->apparent_sex ?? 'No determinado') }} (Aparente)
                        @else
                            {{ ucfirst($episode->patient?->gender ?? 'No especificado') }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="data-label">Teléfono:</td>
                    <td class="data-value">{{ $episode->patient?->phone ?? 'No registrado' }}</td>
                    <td class="data-label">Acompañante Registrado:</td>
                    <td class="data-value">
                        @if ($episode->companion)
                            {{ $episode->companion->name }} ({{ $episode->companion->relationship ?? 'Familiar' }}) - Tel: {{ $episode->companion->phone ?? 'N/A' }}
                        @else
                            <span class="text-muted">Sin acompañante registrado</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    </div>

    {{-- 2. Ingreso y Clasificación de Triage (Resolución 5596 de 2015) --}}
    <div class="section-box">
        <div class="section-header">2. Ingreso y Clasificación de Triage (Res. 5596 de 2015)</div>
        <div class="section-body">
            <table class="data-table">
                <tr>
                    <td class="data-label">Fecha y Hora de Arribo:</td>
                    <td class="data-value">{{ $episode->arrived_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                    <td class="data-label">Origen de Admisión:</td>
                    <td class="data-value">{{ ucfirst(str_replace('_', ' ', $episode->origin)) }}</td>
                </tr>
                <tr>
                    <td class="data-label">Nivel de Triage:</td>
                    <td class="data-value">
                        @switch($episode->triage_level)
                            @case('I')
                                <span class="triage-badge triage-1">Triage I - Resucitación</span>
                                @break
                            @case('II')
                                <span class="triage-badge triage-2">Triage II - Emergencia</span>
                                @break
                            @case('III')
                                <span class="triage-badge triage-3">Triage III - Urgencia</span>
                                @break
                            @case('IV')
                                <span class="triage-badge triage-4">Triage IV - Prioritario</span>
                                @break
                            @case('V')
                                <span class="triage-badge triage-5">Triage V - No Urgente</span>
                                @break
                            @default
                                <span class="text-muted">Pendiente de Triage</span>
                        @endswitch
                    </td>
                    <td class="data-label">Hora de Triage / Espera:</td>
                    <td class="data-value">
                        {{ $episode->triage_at?->format('d/m/Y H:i') ?? 'En proceso' }}
                        @if ($triageWaitMinutes !== null)
                            <span class="text-muted" style="font-size: 7.5pt;">({{ $triageWaitMinutes }} min)</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="data-label">Signos Vitales en Triage:</td>
                    <td class="data-value" colspan="3">
                        @if ($episode->triage_vital_signs && is_array($episode->triage_vital_signs))
                            <span style="font-size: 7.8pt;">
                                PA: <strong>{{ $episode->triage_vital_signs['blood_pressure'] ?? 'N/R' }}</strong> |
                                FC: <strong>{{ $episode->triage_vital_signs['heart_rate'] ?? 'N/R' }} lpm</strong> |
                                FR: <strong>{{ $episode->triage_vital_signs['respiratory_rate'] ?? 'N/R' }} rpm</strong> |
                                T°: <strong>{{ $episode->triage_vital_signs['temperature'] ?? 'N/R' }} °C</strong> |
                                SatO2: <strong>{{ $episode->triage_vital_signs['oxygen_saturation'] ?? 'N/R' }}%</strong> |
                                Glasgow: <strong>{{ $episode->triage_vital_signs['glasgow'] ?? 'N/R' }}/15</strong> |
                                Dolor: <strong>{{ $episode->triage_vital_signs['pain_scale'] ?? 'N/R' }}/10</strong>
                            </span>
                        @else
                            <span class="text-muted">No se registraron signos estructurados en triaje.</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="data-label">Motivo de Consulta:</td>
                    <td class="data-value" colspan="3" style="font-weight: normal;">
                        {{ $episode->reason ?? 'No especificado' }}
                    </td>
                </tr>
            </table>
        </div>
    </div>

    {{-- 3. Ubicación y Equipo Asistencial --}}
    <div class="section-box">
        <div class="section-header">3. Ubicación y Personal Asistencial Asignado</div>
        <div class="section-body">
            <table class="data-table">
                <tr>
                    <td class="data-label">Ubicación Actual / Camilla:</td>
                    <td class="data-value">
                        @if ($episode->activeBedAssignment && $episode->activeBedAssignment->bed)
                            Camilla {{ $episode->activeBedAssignment->bed->code }}
                            ({{ $episode->activeBedAssignment->bed->room?->name ?? 'Habitación' }} - {{ $episode->activeBedAssignment->bed->room?->area?->name ?? 'Área' }})
                        @else
                            <span class="text-muted">Sin camilla asignada</span>
                        @endif
                    </td>
                    <td class="data-label">Médico Responsable:</td>
                    <td class="data-value">
                        @if ($episode->responsibleUser)
                            {{ $episode->responsibleUser->name }}<br>
                            <span style="font-size: 7.5pt; font-weight: normal; color: #555;">
                                Código: {{ $episode->responsibleUser->staffProfile?->code ?? 'N/A' }} | {{ $episode->responsibleUser->staffProfile?->specialty ?? 'Medicina de Urgencias' }}
                            </span>
                        @else
                            <span class="text-muted">Pendiente de asignación</span>
                        @endif
                    </td>
                </tr>
                @if ($episode->supportUsers->count() > 0)
                    <tr>
                        <td class="data-label">Personal de Apoyo:</td>
                        <td class="data-value" colspan="3" style="font-weight: normal;">
                            @foreach ($episode->supportUsers as $supportUser)
                                <span class="font-mono">[{{ $supportUser->staffProfile?->code ?? 'STAFF' }}]</span> {{ $supportUser->name }} ({{ $supportUser->roles->pluck('name')->implode(', ') }})@if(! $loop->last), @endif
                            @endforeach
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    </div>

    {{-- 4. Registro Cronológico de Evolución Clínica y Procedimientos (Res. 1995/1999) --}}
    <div class="section-box">
        <div class="section-header">4. Evolución Clínica, Diagnósticos y Procedimientos (Inmutables)</div>
        <div class="section-body" style="padding: 2.5mm 3mm 1mm 3mm;">
            @if ($episode->clinicalEntries->count() > 0)
                @foreach ($episode->clinicalEntries as $entry)
                    <div class="clinical-entry">
                        <div class="clinical-entry-header">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 50%;">
                                        <span class="clinical-entry-type">{{ $entry->type }}</span> |
                                        <span class="font-mono">{{ $entry->performed_at->format('d/m/Y H:i') }}</span>
                                    </td>
                                    <td style="width: 50%; text-align: right; color: #444;">
                                        <strong>{{ $entry->author?->name ?? 'Personal asistencial' }}</strong>
                                        <span class="font-mono">({{ $entry->author?->staffProfile?->code ?? 'N/A' }})</span>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        @if ($entry->isAnnulled())
                            @php $annulment = $entry->annulments->first(); @endphp
                            <div class="voided-box">
                                <strong>[ENTRADA ANULADA]:</strong> Anulada el {{ $annulment?->performed_at?->format('d/m/Y H:i') }} por {{ $annulment?->author?->name }}.<br>
                                <em>Motivo legal: {{ $annulment?->reason ?? $annulment?->content }}</em>
                            </div>
                        @endif

                        <div class="clinical-entry-body @if ($entry->isAnnulled()) text-muted @endif">
                            {{ $entry->content }}
                        </div>

                        {{-- Notas Aclaratorias Inmutables Enlazadas --}}
                        @if ($entry->clarifications && $entry->clarifications->count() > 0)
                            @foreach ($entry->clarifications as $clarification)
                                <div class="clarification-box">
                                    <strong>Nota Aclaratoria ({{ $clarification->performed_at->format('d/m/Y H:i') }}) por {{ $clarification->author?->name }} ({{ $clarification->author?->staffProfile?->code ?? 'N/A' }}):</strong><br>
                                    {{ $clarification->content }}
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            @else
                <div style="text-align: center; padding: 4mm; color: #888; font-style: italic;">
                    No se han registrado entradas clínicas, diagnósticos ni procedimientos en este episodio.
                </div>
            @endif
        </div>
    </div>

    {{-- 5. Cierre y Destino del Paciente (si aplica) --}}
    @if ($episode->isClosed())
        <div class="section-box">
            <div class="section-header">5. Egreso y Cierre del Episodio de Urgencias</div>
            <div class="section-body">
                <table class="data-table">
                    <tr>
                        <td class="data-label">Fecha y Hora de Egreso:</td>
                        <td class="data-value">{{ ($episode->closed_at ?? $episode->discharged_at)?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                        <td class="data-label">Tipo de Egreso:</td>
                        <td class="data-value">{{ ucfirst(str_replace('_', ' ', $episode->discharge_type ?? 'Alta Médica')) }}</td>
                    </tr>
                    @if ($episode->discharge_notes)
                        <tr>
                            <td class="data-label">Epicrisis / Conducta de Salida:</td>
                            <td class="data-value" colspan="3" style="font-weight: normal;">
                                {{ $episode->discharge_notes }}
                            </td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    @endif

    {{-- Bloque de Custodia y Certificación Legal --}}
    <div style="margin-top: 5mm; page-break-inside: avoid;">
        <table style="width: 100%; border-collapse: collapse; font-size: 7.5pt; color: #444; border-top: 1px solid #aaa; padding-top: 3mm;">
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <strong>CERTIFICACIÓN INSTITUCIONAL DE CUSTODIA</strong><br>
                    Copia fiel de la historia clínica digital generada en el sistema del <strong>Hospital de Ubaté</strong>.<br>
                    Todo acceso, visualización y descarga queda auditado de manera inmutable bajo los lineamientos de la <strong>Resolución 1995 de 1999</strong> y la <strong>Ley Estatutaria 1581 de 2012</strong> de Protección de Datos Personales.
                </td>
                <td style="width: 50%; text-align: right; vertical-align: top;">
                    <strong>RESPONSABLE DE EXPEDICIÓN</strong><br>
                    {{ $staffUser->name }}<br>
                    {{ $staffUser->roles->pluck('name')->implode(', ') }}<br>
                    <span class="font-mono">ID: {{ $staffUser->id }} | Emitido: {{ $emissionTime->format('d/m/Y H:i:s') }}</span>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
