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

    <a href="{{ route('two-factor.show') }}"
        class="list-group-item list-group-item-action {{ request()->routeIs('two-factor.*') ? 'active' : '' }}">
        Verificación en dos pasos
    </a>

    @can('episodios.ver')
        <a href="{{ route('episodes.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('episodes.*') ? 'active' : '' }}">
            <i class="bi bi-hospital me-1" aria-hidden="true"></i> Módulo de Urgencias
        </a>
    @endcan

    @can('ocupacion.ver')
        <a href="{{ route('occupancy.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('occupancy.*') ? 'active' : '' }}">
            Censo y Ocupación
        </a>
    @endcan

    @can('personal.gestionar')
        <a href="{{ route('staff.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('staff.*') ? 'active' : '' }}">
            Personal y Turnos
        </a>
    @endcan

    <a href="{{ route('security.password.edit') }}"
        class="list-group-item list-group-item-action {{ request()->routeIs('security.password.*') ? 'active' : '' }}">
        Cambiar contraseña
    </a>

    <span class="list-group-item text-muted">Páginas <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Servicios <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Galería <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Configuración <small>(próximamente)</small></span>
</div>