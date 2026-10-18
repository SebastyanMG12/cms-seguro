@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row g-4">
    <aside class="col-md-3">
        @include('partials.sidebar')
    </aside>

    <section class="col-md-9">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h1 class="h3">Bienvenido, {{ auth()->user()->name }}</h1>
                <p class="mb-0 text-muted">Administre los contenidos del CMS desde un entorno autenticado.</p>
            </div>
        </div>

        <div class="row g-3">
            @foreach (['Usuarios', 'Noticias', 'Servicios', 'Imágenes'] as $modulo)
                <div class="col-6 col-lg-3">
                    <div class="card text-center shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small">{{ $modulo }}</div>
                            <div class="fs-3 fw-bold">0</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection