<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessage;
use App\Models\ReceivedEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
        if ($request->filled('website')) {
            // Bot detectado: fingimos éxito sin enviar nada.
            return back()->with('success', 'Mensaje enviado correctamente.');
        }

        $data = $request->validated();

        // 1. Guardar de forma persistente en la bandeja de correos recibidos (Módulo Admin /correos)
        ReceivedEmail::create([
            'message_id' => 'contact_'.(string) Str::uuid().'@hospitalubate.gov.co',
            'from_email' => $data['email'],
            'from_name' => $data['name'],
            'subject' => 'Contacto Web: '.Str::limit($data['name'], 50),
            'body' => $data['message'],
            'received_at' => now(),
            'is_read' => false,
        ]);

        // 2. Despachar notificación por correo saliente al buzón administrativo
        try {
            $recipient = config('mail.contact_recipient') ?: config('mail.from.address');
            Mail::to($recipient)->send(
                new ContactMessage($data['name'], $data['email'], $data['message'])
            );
        } catch (Throwable $e) {
            // Se registra el fallo técnico en los logs para diagnóstico sin perder el mensaje radicado
            Log::error('Fallo al enviar notificación de correo de contacto: '.$e->getMessage());
        }

        return back()->with('success', 'Mensaje enviado correctamente. Su solicitud ha sido radicada y notificada a la administración del hospital.');
    }
}
