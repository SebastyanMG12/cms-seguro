<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ReceivedEmailController;

// Rutas públicas
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/acerca', [PageController::class, 'about'])->name('about');
Route::get('/contacto', [ContactController::class, 'create'])->name('contact.create');
Route::post('/contacto', [ContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('contact.store');

// Solo visitantes (no autenticados)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
});

// Solo usuarios autenticados
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn() => view('dashboard'))->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::resource('posts', PostController::class);
    Route::get('/correos', [ReceivedEmailController::class, 'index'])->name('emails.index');
    Route::get('/correos/{email}', [ReceivedEmailController::class, 'show'])
        ->whereNumber('email')
        ->name('emails.show');
});