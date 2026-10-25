<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function requestForm(): View
    {
        return view('auth.passwords.request');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = strtolower(trim($data['email']));

        $user = User::where('email', $email)->where('status', 'active')->first();

        if ($user) {
            // Cooldown: no más de un código por minuto por usuario.
            $recent = PasswordResetCode::where('user_id', $user->id)
                ->where('created_at', '>', now()->subMinute())
                ->exists();

            if (! $recent) {
                // Invalida códigos anteriores.
                PasswordResetCode::where('user_id', $user->id)->whereNull('used_at')
                    ->update(['used_at' => now()]);

                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                PasswordResetCode::create([
                    'user_id' => $user->id,
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes(10),
                ]);
                Audit::log('password_reset_requested', $user, [], $user->id);

                try {
                    Mail::to($user->email)->send(new PasswordResetCodeMail($code));
                } catch (Throwable $e) {
                    Log::error('Fallo al enviar OTP: '.$e->getMessage());
                }
            }
        }

        // Guarda el correo en sesión para el siguiente paso; respuesta siempre igual.
        $request->session()->put('reset_email', $email);

        return redirect()->route('password.verify')
            ->with('success', 'Si la información corresponde a una cuenta registrada, recibirá un código.');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('reset_email')) {
            return redirect()->route('password.request');
        }

        return view('auth.passwords.verify');
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $email = $request->session()->get('reset_email');

        if (! $email) {
            return redirect()->route('password.request');
        }

        $user = User::where('email', $email)->first();
        $record = $user
            ? PasswordResetCode::where('user_id', $user->id)
                ->whereNull('used_at')
                ->latest()
                ->first()
            : null;

        $invalid = back()->withErrors(['code' => 'El código no es válido o expiró.']);

        if (! $record || $record->expires_at->isPast() || $record->attempts >= self::MAX_ATTEMPTS) {
            return $invalid;
        }

        if (! Hash::check($data['code'], $record->code_hash)) {
            $record->increment('attempts');

            return $invalid;
        }

        // Código correcto: se marca verificado (en sesión) y se consume al cambiar la clave.
        $request->session()->put('reset_verified_id', $record->id);

        return redirect()->route('password.reset');
    }

    public function resetForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('reset_verified_id')) {
            return redirect()->route('password.request');
        }

        return view('auth.passwords.reset');
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $recordId = $request->session()->get('reset_verified_id');
        $record = $recordId ? PasswordResetCode::find($recordId) : null;

        if (! $record || $record->used_at || $record->expires_at->isPast()) {
            return redirect()->route('password.request');
        }

        $record->user->forceFill(['password' => Hash::make($data['password'])])->save();
        Audit::log('password_changed', $record->user, [], $record->user->id);
        DB::table('sessions')->where('user_id', $record->user->id)->delete();
        $record->update(['used_at' => now()]);

        // Cierra todas las sesiones y limpia el flujo.
        $request->session()->forget(['reset_email', 'reset_verified_id']);

        return redirect()->route('login')
            ->with('success', 'Contraseña actualizada. Ya puede iniciar sesión.');
    }
}
