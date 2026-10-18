@extends('layouts.app')

@section('title', 'Nueva publicación')

@section('content')
<h1 class="h3 mb-3">Nueva publicación</h1>

<form method="POST" action="{{ route('posts.store') }}" novalidate>
    @csrf
    @include('posts._form')
    <button type="submit" class="btn btn-primary">Guardar</button>
    <a href="{{ route('posts.index') }}" class="btn btn-link">Cancelar</a>
</form>
@endsection