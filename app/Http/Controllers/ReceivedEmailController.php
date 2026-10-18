<?php

namespace App\Http\Controllers;

use App\Models\ReceivedEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReceivedEmailController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', ReceivedEmail::class);

        $emails = ReceivedEmail::orderByDesc('received_at')->paginate(15);

        return view('emails_inbox.index', compact('emails'));
    }

    public function show(ReceivedEmail $email)
    {
        Gate::authorize('view', $email);

        if (! $email->is_read) {
            $email->update(['is_read' => true]);
        }

        return view('emails_inbox.show', compact('email'));
    }
}