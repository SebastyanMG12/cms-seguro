<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserActionReasonRequest;
use App\Models\User;
use App\Services\Audit;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

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

        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('usuarios.gestionar');

        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->forceFill(['status' => 'active'])->save();
            $user->syncRoles($data['roles']);

            Audit::log('user_created', $user, ['roles' => $data['roles']]);

            return $user;
        });

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('usuarios.gestionar');

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->pluck('name'),
            'userRoles' => $user->roles->pluck('name')->all(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('usuarios.gestionar');

        $this->userService->update($user, $request->validated(), $request->user());

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado con éxito.');
    }

    public function suspend(UserActionReasonRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('suspend', $user);

        $this->userService->suspend($user, $request->validated('reason'), $request->user());

        return back()->with('success', 'Usuario suspendido.');
    }

    public function reactivate(UserActionReasonRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('reactivate', $user);

        $this->userService->reactivate($user, $request->validated('reason'), $request->user());

        return back()->with('success', 'Usuario reactivado.');
    }

    public function resetTwoFactor(UserActionReasonRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('resetTwoFactor', $user);

        $this->userService->resetTwoFactor($user, $request->validated('reason'), $request->user());

        return back()->with('success', 'Segundo factor (2FA) restablecido para el usuario.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $this->userService->delete($user, $request->user());

        return redirect()->route('admin.users.index')->with('success', 'Usuario eliminado.');
    }
}
