<div class="list-group shadow-sm">
    <a href="{{ route('dashboard') }}"
       class="list-group-item list-group-item-action {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        Dashboard
    </a>
    <span class="list-group-item text-muted">Usuarios <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Páginas <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Noticias <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Servicios <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Galería <small>(próximamente)</small></span>
    <span class="list-group-item text-muted">Configuración <small>(próximamente)</small></span>
</div>