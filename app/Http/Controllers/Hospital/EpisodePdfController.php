<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\EmergencyEpisode;
use App\Services\PdfReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class EpisodePdfController extends Controller
{
    /**
     * Descarga la historia clínica oficial en formato PDF con marca de agua y auditoría forense.
     */
    public function download(Request $request, EmergencyEpisode $episode, PdfReportService $pdfService): Response
    {
        Gate::authorize('downloadPdf', $episode);

        $pdf = $pdfService->generateClinicalHistoryPdf($episode, auth()->user());

        $filename = sprintf(
            'historia-clinica-%s-%s.pdf',
            $episode->public_code,
            now()->format('Ymd-His')
        );

        return $pdf->download($filename);
    }

    /**
     * Visualiza la historia clínica oficial en el visor PDF del navegador.
     */
    public function stream(Request $request, EmergencyEpisode $episode, PdfReportService $pdfService): Response
    {
        Gate::authorize('downloadPdf', $episode);

        $pdf = $pdfService->generateClinicalHistoryPdf($episode, auth()->user());

        $filename = sprintf(
            'historia-clinica-%s-%s.pdf',
            $episode->public_code,
            now()->format('Ymd-His')
        );

        return $pdf->stream($filename);
    }
}
