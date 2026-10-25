<div class="list-group shadow-sm">
    <a href="{{ route('dashboard') }}"
        class="list-group-item list-group-item-action {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        Dashboard
    </a>
    <a href="{{ route('posts.index') }}"
        class="list-group-item list-group-item-action {{ request()->routeIs('posts.*') ? 'active' : '' }}">
        Publicaciones
    </a>

    @can('correos.ver')
        <a href="{{ route('emails.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('emails.*') ? 'active' : '' }}">
            Correos recibidos
        </a>
    @endcan

    @can('usuarios.gestionar')
        <a href="{{ route('admin.users.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            Usuarios
        </a>
    @endcan

    @can('auditoria.ver')
        <a href="{{ route('audit.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('audit.*') ? 'active' : '' }}">
            Auditoría
        </a>
    @endcan

    <span class="list-group-item text-muted">Páginas <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Servicios <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Galería <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Configuración <small>(próximamente)</small></span>
</div>