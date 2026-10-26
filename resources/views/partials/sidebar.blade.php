<div class="list-group shadow-sm">
    <a href="{{ route('dashboard') }}"
        class="list-group-item list-group-item-action {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i> Panel Principal
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
            <i class="bi bi-door-closed me-1" aria-hidden="true"></i> Censo y Ocupación
        </a>
    @endcan

    @can('personal.gestionar')
        <a href="{{ route('staff.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('staff.*') ? 'active' : '' }}">
            <i class="bi bi-people me-1" aria-hidden="true"></i> Personal y Turnos
        </a>
    @endcan

    @can('usuarios.gestionar')
        <a href="{{ route('admin.users.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <i class="bi bi-person-gear me-1" aria-hidden="true"></i> Gestión de Usuarios
        </a>
    @endcan

    @can('auditoria.ver')
        <a href="{{ route('audit.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('audit.*') ? 'active' : '' }}">
            <i class="bi bi-shield-check me-1" aria-hidden="true"></i> Registro de Auditoría
        </a>
    @endcan

    @can('correos.ver')
        <a href="{{ route('emails.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('emails.*') ? 'active' : '' }}">
            <i class="bi bi-envelope me-1" aria-hidden="true"></i> Correos Recibidos
        </a>
    @endcan

    @hasrole('administrador|autor')
        <a href="{{ route('posts.index') }}"
            class="list-group-item list-group-item-action {{ request()->routeIs('posts.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text me-1" aria-hidden="true"></i> Publicaciones
        </a>
    @endhasrole

    <a href="{{ route('two-factor.show') }}"
        class="list-group-item list-group-item-action {{ request()->routeIs('two-factor.*') ? 'active' : '' }}">
        <i class="bi bi-key me-1" aria-hidden="true"></i> Verificación 2FA
    </a>

    <a href="{{ route('security.password.edit') }}"
        class="list-group-item list-group-item-action {{ request()->routeIs('security.password.*') ? 'active' : '' }}">
        <i class="bi bi-shield-lock me-1" aria-hidden="true"></i> Cambiar Contraseña
    </a>
</div>