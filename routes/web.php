<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hospital\OccupancyController;
use App\Http\Controllers\Hospital\PhysicalResourceController;
use App\Http\Controllers\Hospital\PreregistrationController;
use App\Http\Controllers\Hospital\ShiftController;
use App\Http\Controllers\Hospital\StaffController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ReceivedEmailController;
use App\Http\Controllers\Security\PasswordController;
use App\Http\Controllers\Security\TwoFactorController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/acerca', [PageController::class, 'about'])->name('about');
Route::get('/contacto', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contacto', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

// Pre-registro público de urgencias (sin cuenta)
Route::get('/preregistro', [PreregistrationController::class, 'create'])->name('preregistro.create');
Route::post('/preregistro', [PreregistrationController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('preregistro.store');
Route::get('/preregistro/exito/{code}', [PreregistrationController::class, 'success'])->name('preregistro.success');

// Solo visitantes (no autenticados)
// Registro público cerrado: los usuarios los crea un administrador.
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/login/2fa', [TwoFactorChallengeController::class, 'create'])
        ->name('two-factor.challenge');

    Route::post('/login/2fa', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('two-factor.challenge.store');

    Route::get('/recuperar', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/recuperar', [PasswordResetController::class, 'sendCode'])
        ->middleware('throttle:5,1')->name('password.email');

    Route::get('/recuperar/codigo', [PasswordResetController::class, 'verifyForm'])->name('password.verify');
    Route::post('/recuperar/codigo', [PasswordResetController::class, 'verifyCode'])
        ->middleware('throttle:10,1')->name('password.verify.store');

    Route::get('/recuperar/nueva', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/recuperar/nueva', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1')->name('password.update');
});

// Solo usuarios autenticados, activos y sujetos a la verificación 2FA
Route::middleware(['auth', 'active', 'two-factor'])->group(function () {
    Route::get('/seguridad/2fa', [TwoFactorController::class, 'show'])
        ->name('two-factor.show');

    Route::post('/seguridad/2fa/confirmar', [TwoFactorController::class, 'confirm'])
        ->middleware('throttle:10,1')
        ->name('two-factor.confirm');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::resource('posts', PostController::class);

    Route::get('/correos', [ReceivedEmailController::class, 'index'])->name('emails.index');
    Route::get('/correos/{email}', [ReceivedEmailController::class, 'show'])
        ->whereNumber('email')
        ->name('emails.show');

    Route::get('/auditoria', [AuditLogController::class, 'index'])->name('audit.index');

    Route::get('/seguridad/password', [PasswordController::class, 'edit'])->name('security.password.edit');
    Route::put('/seguridad/password', [PasswordController::class, 'update'])->name('security.password.update');

    Route::get('/admin/usuarios', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/usuarios/crear', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/usuarios', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/usuarios/{user}/editar', [UserController::class, 'edit'])
        ->whereNumber('user')
        ->name('admin.users.edit');
    Route::put('/admin/usuarios/{user}', [UserController::class, 'update'])
        ->whereNumber('user')
        ->name('admin.users.update');
    Route::post('/admin/usuarios/{user}/suspender', [UserController::class, 'suspend'])
        ->whereNumber('user')
        ->name('admin.users.suspend');
    Route::post('/admin/usuarios/{user}/reactivar', [UserController::class, 'reactivate'])
        ->whereNumber('user')
        ->name('admin.users.reactivate');
    Route::post('/admin/usuarios/{user}/restablecer-2fa', [UserController::class, 'resetTwoFactor'])
        ->whereNumber('user')
        ->name('admin.users.reset-2fa');
    Route::delete('/admin/usuarios/{user}', [UserController::class, 'destroy'])
        ->whereNumber('user')
        ->name('admin.users.destroy');

    // Ocupación y censo hospitalario
    Route::get('/ocupacion', [OccupancyController::class, 'index'])->name('occupancy.index');

    // Recursos físicos (camillas y habitaciones)
    Route::post('/habitaciones/{room}/camillas', [PhysicalResourceController::class, 'storeBed'])
        ->whereNumber('room')
        ->name('rooms.beds.store');
    Route::post('/habitaciones/{room}/estado', [PhysicalResourceController::class, 'updateRoomStatus'])
        ->whereNumber('room')
        ->name('rooms.update-status');
    Route::post('/camillas/{bed}/estado', [PhysicalResourceController::class, 'updateBedStatus'])
        ->whereNumber('bed')
        ->name('beds.update-status');
    Route::post('/camillas/{bed}/liberar', [PhysicalResourceController::class, 'releaseBed'])
        ->whereNumber('bed')
        ->name('beds.release');

    // Personal asistencial y turnos
    Route::get('/personal', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/personal', [StaffController::class, 'store'])->name('staff.store');
    Route::post('/personal/{profile}/estado', [StaffController::class, 'updateStatus'])
        ->whereNumber('profile')
        ->name('staff.update-status');
    Route::delete('/personal/{profile}', [StaffController::class, 'destroy'])
        ->whereNumber('profile')
        ->name('staff.destroy');

    Route::post('/turnos', [ShiftController::class, 'store'])->name('shifts.store');
    Route::post('/turnos/{shift}/iniciar', [ShiftController::class, 'start'])
        ->whereNumber('shift')
        ->name('shifts.start');
    Route::post('/turnos/{shift}/finalizar', [ShiftController::class, 'end'])
        ->whereNumber('shift')
        ->name('shifts.end');
});
