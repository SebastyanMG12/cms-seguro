<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('auditoria.ver');

        $logs = AuditLog::with('user')
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('audit.index', compact('logs'));
    }
}