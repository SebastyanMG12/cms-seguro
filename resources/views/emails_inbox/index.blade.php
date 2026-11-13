@extends('layouts.app')

@section('title', 'Correos recibidos')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Correos recibidos</h1>
        <p class="text-muted small mb-0">Buzón de atención al usuario y mensajes de la cuenta oficial ({{ config('imap.accounts.default.username') }}).</p>
    </div>
    <div>
        <form method="POST" action="{{ route('emails.sync') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Sincronizar buzón
            </button>
        </form>
    </div>
</div>

@if ($errors->has('sync'))
    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ $errors->first('sync') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

@if ($emails->isEmpty())
    <div class="alert alert-info shadow-sm">
        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>No hay correos en la bandeja. Pulse en <strong>Sincronizar buzón</strong> para consultar nuevos mensajes desde el servidor de correo.
    </div>
@else
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm">
            <thead class="table-light">
                <tr><th>Remitente</th><th>Asunto</th><th>Fecha</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($emails as $email)
                    <tr class="{{ $email->is_read ? '' : 'fw-bold' }}">
                        <td>{{ $email->from_name ?: $email->from_email }}</td>
                        <td>{{ $email->subject }}</td>
                        <td>{{ optional($email->received_at)->format('d/m/Y H:i') }}</td>
                        <td>
                            <span class="badge {{ $email->is_read ? 'bg-secondary' : 'bg-primary' }}">
                                {{ $email->is_read ? 'Leído' : 'Nuevo' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('emails.show', $email) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $emails->links('pagination::bootstrap-5') }}
@endif
@endsection