<?php

namespace App\Http\Controllers;

use App\Models\ReceivedEmail;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReceivedEmailController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ReceivedEmail::class);

        $emails = ReceivedEmail::orderByDesc('received_at')->paginate(15);

        return response(view('emails_inbox.index', compact('emails')))
            ->header('Cache-Control', 'no-store, max-age=0');
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
