@extends('layouts.app')

@section('title', $post->title)

@section('content')
<article class="card shadow-sm">
    <div class="card-body">
        <h1 class="h3">{{ $post->title }}</h1>
        <p class="text-muted small">
            {{ $post->created_at->format('d/m/Y H:i') }} ·
            {{ $post->status === 'published' ? 'Publicado' : 'Borrador' }}
        </p>
        <div style="white-space: pre-line;">{{ $post->body }}</div>
    </div>
</article>

<div class="mt-3">
    <a href="{{ route('posts.edit', $post) }}" class="btn btn-primary">Editar</a>
    <a href="{{ route('posts.index') }}" class="btn btn-link">Volver</a>
</div>
@endsection