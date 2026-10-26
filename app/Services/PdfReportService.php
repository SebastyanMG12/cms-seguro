<?php

namespace App\Services;

use App\Models\EmergencyEpisode;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Carbon\Carbon;

class PdfReportService
{
    /**
     * Genera la historia clínica oficial en PDF para un episodio de urgencias,
     * aplicando marca de agua de seguridad y registrando el evento de auditoría.
     */
    public function generateClinicalHistoryPdf(EmergencyEpisode $episode, User $staffUser): DomPdfWrapper
    {
        // 1. Cargar relaciones requeridas de forma atómica y sin N+1
        $episode->loadMissing([
            'patient',
            'responsibleUser.staffProfile',
            'supportUsers.staffProfile',
            'activeBedAssignment.bed.room.area',
            'clinicalEntries' => function ($query) {
                $query->with([
                    'author.staffProfile',
                    'clarifications.author.staffProfile',
                    'annulments.author.staffProfile',
                ])->orderBy('performed_at', 'asc');
            },
            'companion',
            'consents',
            'reconciledByUser',
        ]);

        $emissionTime = now();

        // 2. Preparar metadatos para la marca de agua forense
        $userRole = $staffUser->roles->pluck('name')->first() ?? 'Personal asistencial';
        $watermarkText = sprintf(
            'HOSPITAL DE UBATÉ - COPIA OFICIAL | %s (%s) | %s | EPISODIO: %s',
            strtoupper($staffUser->name),
            strtoupper($userRole),
            $emissionTime->format('d/m/Y H:i'),
            $episode->public_code
        );

        // 3. Cálculo de tiempos de espera en triaje y primera atención
        $triageWaitMinutes = null;
        if ($episode->arrived_at && $episode->triage_at) {
            $triageWaitMinutes = (int) $episode->arrived_at->diffInMinutes($episode->triage_at);
        }

        $firstMedicalEntry = $episode->clinicalEntries->first();
        $medicalWaitMinutes = null;
        if ($episode->triage_at && $firstMedicalEntry && $firstMedicalEntry->performed_at) {
            $medicalWaitMinutes = (int) $episode->triage_at->diffInMinutes($firstMedicalEntry->performed_at);
        }

        // 4. Calcular edad del paciente
        $patientAge = null;
        if ($episode->patient?->birth_date) {
            $patientAge = Carbon::parse($episode->patient->birth_date)->age.' años';
        } elseif ($episode->is_nn && $episode->estimated_age) {
            $patientAge = $episode->estimated_age.' años (Estimada)';
        }

        $data = [
            'episode' => $episode,
            'patient' => $episode->patient,
            'staffUser' => $staffUser,
            'emissionTime' => $emissionTime,
            'watermarkText' => $watermarkText,
            'triageWaitMinutes' => $triageWaitMinutes,
            'medicalWaitMinutes' => $medicalWaitMinutes,
            'patientAge' => $patientAge,
        ];

        // 5. Configurar y renderizar PDF
        $pdf = Pdf::loadView('pdf.clinical_history', $data)
            ->setPaper('letter', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'Helvetica');

        // 6. Auditoría inmutable obligatoria de generación de historia clínica
        Audit::log('episodio.pdf_generado', $episode, [
            'public_code' => $episode->public_code,
            'patient_identifier' => $episode->patient?->document_number ?? $episode->nn_code,
            'generated_by_user_id' => $staffUser->id,
            'generated_by_name' => $staffUser->name,
            'generated_by_role' => $userRole,
            'emission_time' => $emissionTime->toIso8601String(),
            'entries_count' => $episode->clinicalEntries->count(),
        ]);

        return $pdf;
    }
}
