<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact.create');
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            Mail::to(config('mail.from.address'))->send(
                new ContactMessage($data['name'], $data['email'], $data['message'])
            );
        } catch (Throwable $e) {
            // Se registra el error técnico, pero al usuario no se le muestra.
            Log::error('Fallo al enviar correo de contacto: '.$e->getMessage());

            return back()
                ->withInput()
                ->withErrors(['message' => 'No fue posible enviar el mensaje. Intente más tarde.']);
        }

        return back()->with('success', 'Mensaje enviado correctamente.');
    }
}