@extends('layouts.app')

@section('title', 'Contacto')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <h1 class="h3 mb-3">Contacto</h1>

        <form method="POST" action="{{ route('contact.store') }}" novalidate>
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Nombre</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}"
                       class="form-control @error('name') is-invalid @enderror" maxlength="100" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Correo</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror" maxlength="255" required>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label for="message" class="form-label">Mensaje</label>
                <textarea id="message" name="message" rows="6"
                          class="form-control @error('message') is-invalid @enderror"
                          maxlength="5000" required>{{ old('message') }}</textarea>
                @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary">Enviar mensaje</button>
        </form>
    </div>
</div>
@endsection