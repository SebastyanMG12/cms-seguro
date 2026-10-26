<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-white d-flex align-items-center" href="{{ route('home') }}">
            <i class="bi bi-hospital me-2 fs-5" aria-hidden="true"></i>
            <span>{{ config('app.name', 'Hospital de Ubaté') }}</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal"
            aria-controls="menuPrincipal" aria-expanded="false" aria-label="Mostrar menú principal">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuPrincipal">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('home') ? 'active fw-semibold' : '' }}"
                        href="{{ route('home') }}">Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('about') ? 'active fw-semibold' : '' }}"
                        href="{{ route('about') }}">Acerca</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-warning fw-semibold {{ request()->routeIs('preregistro.*') ? 'active' : '' }}"
                        href="{{ route('preregistro.create') }}">
                        <i class="bi bi-card-checklist me-1" aria-hidden="true"></i>Pre-registro Urgencias
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-1">
                @auth
                    @can('episodios.ver')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('episodes.*') ? 'active fw-semibold' : '' }}"
                                href="{{ route('episodes.index') }}">
                                <i class="bi bi-heart-pulse me-1" aria-hidden="true"></i>Urgencias
                            </a>
                        </li>
                    @endcan
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active fw-semibold' : '' }}"
                            href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2 me-1" aria-hidden="true"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm px-3 py-1">
                                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Cerrar sesión
                            </button>
                        </form>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('contact.*') ? 'active fw-semibold' : '' }}"
                            href="{{ route('contact.create') }}">
                            <i class="bi bi-envelope me-1" aria-hidden="true"></i>Contacto
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-info fw-semibold {{ request()->routeIs('companion.*') ? 'active' : '' }}"
                            href="{{ route('companion.login') }}">
                            <i class="bi bi-people me-1" aria-hidden="true"></i>Acompañantes
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-primary btn-sm px-3 py-1 {{ request()->routeIs('login') ? 'active' : '' }}"
                            href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i>Ingresar
                        </a>
                    </li>
                @endauth
            </ul>

        </div>
    </div>
</nav>