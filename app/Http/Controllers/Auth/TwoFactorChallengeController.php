<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use App\Services\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private TwoFactor $twoFactor) {}

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return response(view('auth.two-factor-challenge'))
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);

        $valid = false;

        if (! empty($data['code'])) {
            $timestep = $this->twoFactor->verifyAndGetTimestep(
                $user->two_factor_secret,
                $data['code'],
                $user->two_factor_last_timestep
            );

            if ($timestep !== false) {
                $user->forceFill(['two_factor_last_timestep' => $timestep])->save();
                $valid = true;
            }
        } elseif (! empty($data['recovery_code'])) {
            $valid = $this->consumeRecoveryCode($user, $data['recovery_code']);
        }

        if (! $valid) {
            Audit::log('two_factor_failed', null, [], $user->id);

            return back()->withErrors(['code' => 'El código no es válido.']);
        }

        $remember = (bool) $request->session()->pull('two_factor.remember', false);
        $request->session()->forget(['two_factor.user_id', 'two_factor.expires_at']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        Audit::log('login');

        return redirect()->intended(route('dashboard'));
    }

    private function pendingUser(Request $request): ?User
    {
        $id = $request->session()->get('two_factor.user_id');
        $expires = $request->session()->get('two_factor.expires_at');

        if (! $id || ! $expires || now()->timestamp > $expires) {
            $request->session()->forget(['two_factor.user_id', 'two_factor.expires_at', 'two_factor.remember']);

            return null;
        }

        $user = User::find($id);

        return $user && $user->isActive() && $user->hasTwoFactorEnabled() ? $user : null;
    }

    private function consumeRecoveryCode(User $user, string $input): bool
    {
        $input = strtolower(trim($input));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hash) {
            if (Hash::check($input, $hash)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                Audit::log('two_factor_recovery_used', null, ['remaining' => count($codes)], $user->id);

                return true;
            }
        }

        return false;
    }
}
