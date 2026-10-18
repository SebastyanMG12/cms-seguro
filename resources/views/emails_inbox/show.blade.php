@extends('layouts.app')

@section('title', 'Correo')

@section('content')
<article class="card shadow-sm">
    <div class="card-body">
        <h1 class="h4">{{ $email->subject }}</h1>
        <p class="text-muted small mb-3">
            De: {{ $email->from_name }} &lt;{{ $email->from_email }}&gt; ·
            {{ optional($email->received_at)->format('d/m/Y H:i') }}
        </p>
        <div style="white-space: pre-line;">{{ $email->body }}</div>
    </div>
</article>
<a href="{{ route('emails.index') }}" class="btn btn-link mt-3">Volver</a>
@endsection