@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Usuarios</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Nuevo usuario</a>
</div>
<div class="table-responsive">
<table class="table table-hover bg-white shadow-sm align-middle">
    <thead class="table-light"><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th></tr></thead>
    <tbody>
    @foreach ($users as $u)
        <tr>
            <td>{{ $u->name }}</td>
            <td>{{ $u->email }}</td>
            <td><span class="badge bg-secondary">{{ $u->role }}</span></td>
            <td>{{ $u->status }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
{{ $users->links('pagination::bootstrap-5') }}
@endsection