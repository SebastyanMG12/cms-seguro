@extends('layouts.app')

@section('title', 'Noticias y Comunicados Institucionales - Hospital de Ubaté')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-lg-10">

        {{-- Encabezado --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 pb-2 border-bottom">
            <div>
                <h1 class="h3 fw-bold text-dark mb-1">Noticias y Comunicados Institucionales</h1>
                <p class="text-muted small mb-0">
                    Información oficial, avisos a la comunidad y actualidad de los servicios del Hospital de Ubaté.
                </p>
            </div>
            @auth
                @can('create', App\Models\Post::class)
                    <a href="{{ route('posts.create') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Redactar Noticia
                    </a>
                @endcan
            @endauth
        </div>

        {{-- Listado de Noticias --}}
        @if ($news->count() > 0)
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
                @foreach ($news as $item)
                    <div class="col">
                        <div class="card h-100 shadow-sm border-0 border-top border-3 border-primary">
                            <div class="card-body d-flex flex-column p-4">
                                <div class="d-flex justify-content-between align-items-center small text-muted mb-2">
                                    <span class="font-monospace tabular-nums">
                                        <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ $item->created_at->format('d/m/Y') }}
                                    </span>
                                    <span class="badge bg-light text-dark border">Oficial</span>
                                </div>
                                <h2 class="h5 card-title fw-bold text-dark mb-2">
                                    <a href="{{ route('news.show', $item->slug) }}" class="text-decoration-none text-dark hover-primary">
                                        {{ $item->title }}
                                    </a>
                                </h2>
                                <p class="card-text text-secondary small flex-grow-1">
                                    {{ Str::limit(strip_tags($item->body), 140) }}
                                </p>
                                <div class="pt-3 border-top mt-auto d-flex justify-content-between align-items-center">
                                    <span class="small text-muted">
                                        <i class="bi bi-person me-1" aria-hidden="true"></i>{{ $item->user?->name ?? 'Hospital de Ubaté' }}
                                    </span>
                                    <a href="{{ route('news.show', $item->slug) }}" class="btn btn-outline-primary btn-sm py-1 px-3">
                                        Leer más <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Paginación --}}
            <div class="d-flex justify-content-center">
                {{ $news->links() }}
            </div>
        @else
            <div class="card shadow-sm border-0 p-5 text-center bg-white my-4">
                <div class="py-4">
                    <i class="bi bi-newspaper display-3 text-muted mb-3 d-block" aria-hidden="true"></i>
                    <h3 class="h5 fw-bold text-dark">No hay publicaciones disponibles</h3>
                    <p class="text-muted small mb-0">
                        Próximamente se publicarán aquí las noticias y avisos oficiales para la comunidad.
                    </p>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
