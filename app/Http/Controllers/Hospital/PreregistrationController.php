<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreregistrationRequest;
use App\Models\EmergencyEpisode;
use App\Services\PreregistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PreregistrationController extends Controller
{
    public function create(): View
    {
        return view('preregistration.create');
    }

    public function store(PreregistrationRequest $request, PreregistrationService $service): RedirectResponse
    {
        // Trampa Honeypot: si el campo oculto contiene valor, fingir éxito para mitigar robots
        if (! empty($request->input('hospital_contact_time'))) {
            return redirect()->route('preregistro.success', ['code' => 'UBT8K9P2']);
        }

        $episode = $service->createPreregistration(
            $request->validated(),
            $request->ip(),
            $request->userAgent()
        );

        return redirect()->route('preregistro.success', ['code' => $episode->public_code])
            ->with('status', 'Pre-registro generado exitosamente.');
    }

    public function success(string $code): View
    {
        // Validar únicamente formato del código de alta entropía (8 a 10 caracteres alfanuméricos)
        if (! preg_match('/^[2-9A-HJ-NP-Z]{8,10}$/', $code)) {
            abort(404, 'Código de pre-registro no encontrado.');
        }

        $episode = EmergencyEpisode::where('public_code', $code)
            ->whereIn('status', ['preregistrado', 'en_espera'])
            ->firstOrFail();

        // No se envían datos clínicos sensibles ni de salud a la vista pública de confirmación
        return view('preregistration.success', [
            'publicCode' => $episode->public_code,
            'createdAt' => $episode->created_at,
            'expiresAt' => $episode->pre_registration_expires_at,
        ]);
    }
}
