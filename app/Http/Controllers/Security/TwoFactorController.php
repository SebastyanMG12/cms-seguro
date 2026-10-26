<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwoFactorController extends Controller
{
    public function __construct(private TwoFactor $twoFactor) {}

    public function show(Request $request): Response
    {
        $user = $request->user();

        $qr = null;
        $secret = null;

        if (! $user->hasTwoFactorEnabled()) {
            if (! $user->two_factor_secret) {
                $user->forceFill(['two_factor_secret' => $this->twoFactor->generateSecret()])->save();
            }

            $secret = $user->two_factor_secret;
            $qr = $this->twoFactor->qrSvg($user, $secret);
        }

        return response(view('security.two-factor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'qr' => $qr,
            'secret' => $secret,
            'recoveryCodes' => session('recovery_codes'),
        ]))->header('Cache-Control', 'no-store, max-age=0');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled() || ! $user->two_factor_secret) {
            return redirect()->route('two-factor.show');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        if (! $this->twoFactor->verify($user->two_factor_secret, $data['code'])) {
            Audit::log('two_factor_confirm_failed');

            return back()->withErrors(['code' => 'El código no es válido.']);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes['hashed'],
        ])->save();

        Audit::log('two_factor_enabled');

        return redirect()->route('two-factor.show')->with('recovery_codes', $codes['plain']);
    }
}
