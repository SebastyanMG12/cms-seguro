@extends('layouts.app')

@section('title', 'Verificación en dos pasos')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-3">Verificación en dos pasos</h1>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('two-factor.challenge.store') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="code" class="form-label">Código de la aplicación (6 dígitos)</label>
                        <input id="code" name="code" inputmode="numeric" maxlength="6"
                               autocomplete="one-time-code" class="form-control" autofocus>
                    </div>
                    <button class="btn btn-primary w-100">Verificar</button>
                </form>

                <hr>

                <form method="POST" action="{{ route('two-factor.challenge.store') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <label for="recovery_code" class="form-label">O un código de recuperación</label>
                        <input id="recovery_code" name="recovery_code" maxlength="20"
                               autocomplete="off" class="form-control font-monospace">
                    </div>
                    <button class="btn btn-outline-secondary w-100">Usar código de recuperación</button>
                </form>

                <p class="mt-3 mb-0 text-center"><a href="{{ route('login') }}">Cancelar</a></p>
            </div>
        </div>
    </div>
</div>
@endsection