@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="mb-3">Bienvenido, {{ auth()->user()->name }}</h1>
    <p>El acceso a esta página requiere autenticación.</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn btn-outline-danger">Cerrar sesión</button>
    </form>
@endsection