@extends('layouts.app')

@section('title', 'Correos recibidos')

@section('content')
<h1 class="h3 mb-3">Correos recibidos</h1>

@if ($emails->isEmpty())
    <div class="alert alert-info">No hay correos. Ejecute <code>php artisan mail:receive</code>.</div>
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