<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function edit(): View
    {
        return view('security.password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $this->userService->changePassword(
            $request->user(),
            $request->validated('password'),
            $request->session()->getId()
        );

        return back()->with('success', 'Contraseña actualizada correctamente y demás sesiones cerradas.');
    }
}
