@extends('layouts.app')

@section('title', 'Editar publicación')

@section('content')
<h1 class="h3 mb-3">Editar publicación</h1>

<form method="POST" action="{{ route('posts.update', $post) }}" novalidate>
    @csrf
    @method('PUT')
    @include('posts._form')
    <button type="submit" class="btn btn-primary">Actualizar</button>
    <a href="{{ route('posts.index') }}" class="btn btn-link">Cancelar</a>
</form>
@endsection