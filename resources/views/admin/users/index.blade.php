@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Usuarios</h1>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Nuevo usuario</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover bg-white shadow-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>
                            @foreach ($u->roles as $r)
                                <span class="badge bg-secondary">{{ $r->name }}</span>
                            @endforeach
                        </td>
                        <td>
                            @if ($u->status === 'active')
                                <span class="badge bg-success">Activo</span>
                            @else
                                <span class="badge bg-danger">Suspendido</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links('pagination::bootstrap-5') }}
@endsection