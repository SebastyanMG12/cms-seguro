@extends('layouts.app')

@section('title', $post->title . ' - Hospital de Ubaté')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-lg-8">

        {{-- Migas de pan / Navegación superior --}}
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ url('/') }}" class="text-decoration-none">Inicio</a></li>
                <li class="breadcrumb-item"><a href="{{ route('news.index') }}" class="text-decoration-none">Noticias</a></li>
                <li class="breadcrumb-item active text-truncate" style="max-width: 300px;" aria-current="page">{{ $post->title }}</li>
            </ol>
        </nav>

        {{-- Artículo --}}
        <article class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4 p-md-5">

                {{-- Metadatos --}}
                <div class="d-flex align-items-center gap-3 small text-muted mb-3 pb-2 border-bottom">
                    <span>
                        <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ $post->created_at->format('d/m/Y') }}
                    </span>
                    <span>
                        <i class="bi bi-person me-1" aria-hidden="true"></i>Por: {{ $post->user?->name ?? 'Hospital de Ubaté' }}
                    </span>
                    <span class="badge bg-success-subtle text-success-emphasis border ms-auto">Comunicado Oficial</span>
                </div>

                {{-- Título --}}
                <h1 class="h2 fw-bold text-dark mb-4">{{ $post->title }}</h1>

                {{-- Cuerpo del artículo (Escapado estricto contra XSS con saltos de línea) --}}
                <div class="article-content text-dark lh-lg" style="font-size: 1.05rem;">
                    {!! nl2br(e($post->body)) !!}
                </div>

                <div class="pt-4 mt-5 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <a href="{{ route('news.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Volver a Noticias
                    </a>
                    @auth
                        @can('update', $post)
                            <a href="{{ route('posts.edit', $post) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil me-1" aria-hidden="true"></i> Editar Noticia
                            </a>
                        @endcan
                    @endauth
                </div>

            </div>
        </article>

    </div>
</div>
@endsection
