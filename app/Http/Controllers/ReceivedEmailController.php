<?php

namespace App\Http\Controllers;

use App\Models\ReceivedEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReceivedEmailController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ReceivedEmail::class);

        $emails = ReceivedEmail::orderByDesc('received_at')->paginate(15);

        return response(view('emails_inbox.index', compact('emails')))
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    public function sync(): RedirectResponse
    {
        Gate::authorize('viewAny', ReceivedEmail::class);

        try {
            Artisan::call('mail:receive', ['--limit' => 25]);
            $output = trim(Artisan::output());

            return redirect()->route('emails.index')
                ->with('success', 'Bandeja sincronizada con el servidor de correo exitosamente. ('.$output.')');
        } catch (Throwable $e) {
            Log::error('Fallo al sincronizar correos con IMAP: '.$e->getMessage());

            return redirect()->route('emails.index')
                ->withErrors(['sync' => 'No fue posible sincronizar con el buzón: '.$e->getMessage()]);
        }
    }

    public function show(ReceivedEmail $email): Response
    {
        Gate::authorize('view', $email);

        if (! $email->is_read) {
            $email->update(['is_read' => true]);
        }

        return response(view('emails_inbox.show', compact('email')))
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
