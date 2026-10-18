@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
    <div class="p-5 mb-4 bg-white border rounded-3 shadow-sm">
        <h1 class="display-5 fw-bold">CMS Seguro</h1>
        <p class="lead">Aplicación construida con seguridad desde el diseño.</p>
        <a class="btn btn-primary" href="{{ route('about') }}">Conocer más</a>
    </div>
@endsection