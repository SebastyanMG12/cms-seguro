<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Hospital de Ubaté') | {{ config('app.name', 'Hospital de Ubaté') }}</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100 bg-light">

    @include('partials.navbar')

    <main class="container py-4 flex-grow-1">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-1" aria-hidden="true"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="bg-dark text-light py-4 mt-auto border-top border-secondary">
        <div class="container small">
            <div class="row align-items-center gy-2">
                <div class="col-12 col-md-6 text-center text-md-start">
                    <span class="fw-semibold">{{ config('app.name', 'Hospital de Ubaté') }}</span> &mdash; Servicio de Atención de Urgencias 24/7
                </div>
                <div class="col-12 col-md-6 text-center text-md-end text-secondary">
                    &copy; {{ date('Y') }} Hospital de Ubaté &bull; <a href="{{ route('documentation') }}" class="text-secondary text-decoration-none"><i class="bi bi-journal-code me-1" aria-hidden="true"></i>Documentación</a> &bull; Res. 1995/1999 y 5596/2015
                </div>

            </div>
        </div>
    </footer>

</body>
</html>