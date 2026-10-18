<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CMS Seguro') | {{ config('app.name') }}</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body class="d-flex flex-column min-vh-100">

    @include('partials.navbar')

    <main class="container py-5 flex-grow-1">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="bg-dark text-light py-3 mt-auto">
        <div class="container text-center small">
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </footer>

</body>
</html>