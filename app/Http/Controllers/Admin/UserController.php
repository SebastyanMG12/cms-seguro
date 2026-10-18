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

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-users');

        return view('admin.users.index', ['users' => User::orderBy('name')->paginate(20)]);
    }

    public function create(): View
    {
        Gate::authorize('manage-users');

        return view('admin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('manage-users');

        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $user->forceFill(['role' => $data['role'], 'status' => 'active'])->save();

        Audit::log('user_created', $user, ['role' => $data['role']]);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado.');
    }
}