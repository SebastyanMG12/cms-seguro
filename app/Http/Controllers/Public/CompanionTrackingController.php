<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\EmergencyEpisode;
use App\Services\CompanionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class CompanionTrackingController extends Controller
{
    /**
     * Muestra la pantalla de acceso para acompañantes.
     */
    public function loginForm(): View|RedirectResponse
    {
        if (session()->has('companion_episode_id')) {
            return redirect()->route('companion.status');
        }

        return view('companion.login');
    }

    /**
     * Valida el código de paciente y el PIN presencial, aplicando rate limiting y anti-enumeración.
     */
    public function authenticate(Request $request, CompanionService $companionService): RedirectResponse
    {
        $code = strtoupper(trim((string) $request->input('public_code', '')));
        $throttleKey = 'companion_login:'.$request->ip().':'.$code;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('public_code'))
                ->withErrors([
                    'auth' => "Demasiados intentos fallidos. Por seguridad, el acceso está bloqueado temporalmente. Intente nuevamente en {$seconds} segundos.",
                ]);
        }

        $validated = $request->validate([
            'public_code' => ['required', 'string', 'min:6', 'max:12'],
            'pin' => ['required', 'string', 'digits_between:4,6'],
        ], [
            'public_code.required' => 'El código del paciente es obligatorio.',
            'pin.required' => 'El PIN numérico es obligatorio.',
            'pin.digits_between' => 'El PIN debe ser numérico de 4 a 6 dígitos.',
        ]);

        $result = $companionService->verifyAccess($validated['public_code'], $validated['pin']);

        if (! $result['success']) {
            RateLimiter::hit($throttleKey, 60);

            if ($result['status'] === 'revoked') {
                return back()
                    ->withInput($request->only('public_code'))
                    ->withErrors([
                        'auth' => 'El acceso para el acompañante de este paciente ha sido revocado por razones de custodia o solicitud del paciente.',
                    ]);
            }

            if ($result['status'] === 'expired') {
                return back()
                    ->withInput($request->only('public_code'))
                    ->withErrors([
                        'auth' => 'El tiempo de consulta para este episodio ha expirado tras el egreso del paciente.',
                    ]);
            }

            // Error genérico anti-enumeración
            return back()
                ->withInput($request->only('public_code'))
                ->withErrors([
                    'auth' => 'Los datos no coinciden. Verifique el código del paciente y el PIN entregado por el personal asistencial.',
                ]);
        }

        RateLimiter::clear($throttleKey);

        session(['companion_episode_id' => $result['episode']->id]);

        return redirect()->route('companion.status');
    }

    /**
     * Muestra la pantalla de seguimiento en tiempo real con datos estrictamente minimizados.
     */
    public function status(): View|RedirectResponse
    {
        $episodeId = session('companion_episode_id');
        if (! $episodeId) {
            return redirect()->route('companion.login');
        }

        $episode = EmergencyEpisode::with([
            'patient',
            'companion',
            'consents',
            'activeBedAssignment.bed.room.area',
            'clinicalEntries' => function ($q) {
                $q->whereIn('type', ['diagnostico', 'procedimiento'])->orderBy('performed_at', 'asc');
            },
        ])->find($episodeId);

        if (! $episode || ! $episode->companion) {
            session()->forget('companion_episode_id');

            return redirect()->route('companion.login')
                ->withErrors(['auth' => 'La sesión de consulta ha expirado.']);
        }

        if ($episode->companion->revoked_at !== null) {
            session()->forget('companion_episode_id');

            return redirect()->route('companion.login')
                ->withErrors(['auth' => 'El acceso para el acompañante de este paciente ha sido revocado.']);
        }

        if ($episode->isCompanionAccessExpired()) {
            session()->forget('companion_episode_id');

            return redirect()->route('companion.login')
                ->withErrors(['auth' => 'El acceso para este episodio ha finalizado tras el egreso del paciente.']);
        }

        return view('companion.status', [
            'episode' => $episode,
            'maskedName' => $episode->masked_patient_name,
            'hasConsent' => $episode->hasActiveCompanionConsent(),
            'activeConsent' => $episode->activeCompanionConsent(),
        ]);
    }

    /**
     * Cierra la sesión de consulta del acompañante.
     */
    public function logout(): RedirectResponse
    {
        session()->forget('companion_episode_id');

        return redirect()->route('companion.login')
            ->with('info', 'Consulta de acompañante finalizada.');
    }
}
