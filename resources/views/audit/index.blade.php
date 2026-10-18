@extends('layouts.app')
@section('title', 'Auditoría')
@section('content')
<h1 class="h3 mb-3">Auditoría</h1>

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <input type="text" name="event" value="{{ request('event') }}" class="form-control" placeholder="Evento (ej. login)">
    </div>
    <div class="col-auto"><button class="btn btn-outline-primary">Filtrar</button></div>
</form>

<div class="table-responsive">
<table class="table table-sm table-hover bg-white shadow-sm align-middle">
    <thead class="table-light">
        <tr><th>Fecha</th><th>Usuario</th><th>Evento</th><th>Objeto</th><th>IP</th><th>Detalle</th></tr>
    </thead>
    <tbody>
        @forelse ($logs as $log)
            <tr>
                <td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                <td>{{ $log->user?->email ?? '-' }}</td>
                <td><span class="badge bg-secondary">{{ $log->event }}</span></td>
                <td>{{ $log->model_type }} {{ $log->model_id }}</td>
                <td>{{ $log->ip_address }}</td>
                <td class="small">{{ $log->metadata ? json_encode($log->metadata) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted">Sin eventos.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
{{ $logs->links('pagination::bootstrap-5') }}
@endsection