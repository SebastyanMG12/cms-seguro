@extends('layouts.app')

@section('title', 'Publicaciones')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Mis publicaciones</h1>
    <a href="{{ route('posts.create') }}" class="btn btn-primary">Nueva publicación</a>
</div>

@if ($posts->isEmpty())
    <div class="alert alert-info">Aún no tiene publicaciones.</div>
@else
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm">
            <thead class="table-light">
                <tr>
                    <th>Título</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($posts as $post)
                    <tr>
                        <td>{{ $post->title }}</td>
                        <td>
                            <span class="badge {{ $post->status === 'published' ? 'bg-success' : 'bg-secondary' }}">
                                {{ $post->status === 'published' ? 'Publicado' : 'Borrador' }}
                            </span>
                        </td>
                        <td>{{ $post->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            <a href="{{ route('posts.show', $post) }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                            <a href="{{ route('posts.edit', $post) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                            <form method="POST" action="{{ route('posts.destroy', $post) }}"
                                  class="d-inline" onsubmit="return confirm('¿Eliminar esta publicación?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $posts->links('pagination::bootstrap-5') }}
@endif
@endsection