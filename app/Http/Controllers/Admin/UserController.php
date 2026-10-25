<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('usuarios.gestionar');

        return view('admin.users.index', [
            'users' => User::with('roles')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('usuarios.gestionar');

        return view('admin.users.create', ['roles' => Role::orderBy('name')->pluck('name')]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('usuarios.gestionar');

        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->syncRoles($data['roles']);

        Audit::log('user_created', $user, ['roles' => $data['roles']]);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado.');
    }
}