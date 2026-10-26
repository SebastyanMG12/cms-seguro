<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\EmergencyEpisode;
use App\Services\CompanionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanionManagementController extends Controller
{
    /**
     * Genera o regenera un PIN presencial para el acompañante del episodio.
     */
    public function generatePin(EmergencyEpisode $episode, CompanionService $companionService): RedirectResponse
    {
        Gate::authorize('view', $episode);

        $plainPin = $companionService->generatePin($episode, auth()->user());

        return redirect()->route('episodes.show', $episode)
            ->with('success', 'PIN presencial generado exitosamente. Entregue este código en persona al acompañante.')
            ->with('generated_pin', $plainPin);
    }

    /**
     * Revoca el acceso del acompañante al seguimiento del paciente.
     */
    public function revokeAccess(Request $request, EmergencyEpisode $episode, CompanionService $companionService): RedirectResponse
    {
        Gate::authorize('view', $episode);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $companionService->revokeAccess($episode, auth()->user(), $validated['reason'] ?? null);

        return redirect()->route('episodes.show', $episode)
            ->with('success', 'Acceso del acompañante revocado formalmente.');
    }

    /**
     * Registra o revoca la autorización del paciente para compartir información clínica con su acompañante.
     */
    public function toggleConsent(Request $request, EmergencyEpisode $episode, CompanionService $companionService): RedirectResponse
    {
        Gate::authorize('view', $episode);

        $validated = $request->validate([
            'enable' => ['required', 'boolean'],
            'granted_by' => ['nullable', 'string', 'in:paciente,representante'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $companionService->toggleClinicalConsent(
            $episode,
            auth()->user(),
            (bool) $validated['enable'],
            $validated['granted_by'] ?? 'paciente',
            $validated['relationship'] ?? null
        );

        $actionText = $validated['enable'] ? 'autorizada' : 'revocada';

        return redirect()->route('episodes.show', $episode)
            ->with('success', "Autorización para compartir información clínica con acompañante {$actionText}.");
    }
}
