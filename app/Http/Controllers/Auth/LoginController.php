<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Services\Audit;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $credentials['status'] = 'active';

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            Audit::log('login_failed', null, ['email' => strtolower($request->input('email'))]);
            return back()
                ->withErrors(['email' => 'Las credenciales proporcionadas no son válidas.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        Audit::log('login');

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Audit::log('logout');
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}