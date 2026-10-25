@extends('layouts.app')

@section('title', 'Censo y Ocupación Hospitalaria')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0 fw-bold">Censo y Ocupación Hospitalaria</h1>
        <p class="text-muted small mb-0">Monitoreo en tiempo real de áreas, habitaciones y disponibilidad de camillas.</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger py-2" role="alert">
        <ul class="mb-0 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Tarjetas de métricas consolidadas en formato compacto y numérico tabular --}}
<div class="row g-2 mb-3">
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="card shadow-sm border text-center p-2 bg-white card-compact">
            <span class="text-muted small fw-semibold text-uppercase d-block">Total Camillas</span>
            <span class="h4 mb-0 fw-bold text-dark tabular-nums">{{ $report['total_beds'] }}</span>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="card shadow-sm border text-center p-2 bg-white card-compact">
            <span class="text-muted small fw-semibold text-uppercase d-block">
                <i class="bi bi-check-circle text-success me-1" aria-hidden="true"></i>Disponibles
            </span>
            <span class="h4 mb-0 fw-bold text-success tabular-nums">{{ $report['beds_by_status']['disponible'] ?? 0 }}</span>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="card shadow-sm border text-center p-2 bg-white card-compact">
            <span class="text-muted small fw-semibold text-uppercase d-block">
                <i class="bi bi-person text-danger me-1" aria-hidden="true"></i>Ocupadas
            </span>
            <span class="h4 mb-0 fw-bold text-danger tabular-nums">{{ $report['beds_by_status']['ocupada'] ?? 0 }}</span>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="card shadow-sm border text-center p-2 bg-white card-compact">
            <span class="text-muted small fw-semibold text-uppercase d-block">
                <i class="bi bi-stars text-warning me-1" aria-hidden="true"></i>Limpieza
            </span>
            <span class="h4 mb-0 fw-bold text-warning tabular-nums">{{ $report['beds_by_status']['en_limpieza'] ?? 0 }}</span>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="card shadow-sm border text-center p-2 bg-white card-compact">
            <span class="text-muted small fw-semibold text-uppercase d-block">
                <i class="bi bi-tools text-secondary me-1" aria-hidden="true"></i>Mantenimiento
            </span>
            <span class="h4 mb-0 fw-bold text-secondary tabular-nums">{{ $report['beds_by_status']['mantenimiento'] ?? 0 }}</span>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-lg-2">
        <div class="card shadow-sm border text-center p-2 bg-white card-compact">
            <span class="text-muted small fw-semibold text-uppercase d-block">
                <i class="bi bi-door-closed text-primary me-1" aria-hidden="true"></i>Hab. Llenas
            </span>
            <span class="h4 mb-0 fw-bold text-primary tabular-nums">{{ $report['full_rooms'] }} / {{ $report['total_rooms'] }}</span>
        </div>
    </div>
</div>

{{-- Detalle por Sedes, Pisos y Habitaciones en alta densidad --}}
@forelse ($sites as $site)
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-dark text-white py-2 px-3 d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0 fw-bold"><i class="bi bi-hospital me-2" aria-hidden="true"></i>{{ $site->name }} ({{ $site->code }})</h2>
            <span class="badge {{ $site->isActive() ? 'bg-success' : 'bg-secondary' }}">
                <i class="bi {{ $site->isActive() ? 'bi-check' : 'bi-dash' }}" aria-hidden="true"></i> {{ ucfirst($site->status) }}
            </span>
        </div>
        <div class="card-body p-3">
            @forelse ($site->floors as $floor)
                <div class="mb-3">
                    <h3 class="h6 text-uppercase fw-bold text-secondary border-bottom pb-1 small">
                        <i class="bi bi-layers me-1" aria-hidden="true"></i> Piso {{ $floor->number }} - {{ $floor->name }}
                    </h3>

                    @forelse ($floor->areas as $area)
                        <div class="ms-md-2 mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <h4 class="h6 mb-0 fw-bold text-primary">{{ $area->name }}</h4>
                                <span class="badge bg-light text-dark border ms-2 small">{{ ucfirst($area->type) }}</span>
                            </div>

                            <div class="row g-2">
                                @forelse ($area->rooms as $room)
                                    <div class="col-md-6 col-xl-4">
                                        <div class="card h-100 border {{ $room->isFull() ? 'border-danger' : 'border-light-subtle' }} card-compact">
                                            <div class="card-header py-1 px-2 d-flex justify-content-between align-items-center bg-light">
                                                <div>
                                                    <strong class="small">Hab. {{ $room->code }}</strong>
                                                    <small class="text-muted tabular-nums">({{ $room->beds->count() }}/{{ $room->capacity }})</small>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    @if ($room->isFull())
                                                        <span class="badge bg-danger small"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>Llena</span>
                                                    @endif
                                                    @if ($room->is_isolation)
                                                        <span class="badge bg-info text-dark small">Aislamiento</span>
                                                    @endif
                                                    @if ($room->is_pediatric)
                                                        <span class="badge bg-primary small">Pediátrica</span>
                                                    @endif
                                                    @if ($room->status !== 'disponible')
                                                        <span class="badge bg-secondary small">{{ ucfirst($room->status) }}</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="card-body p-1">
                                                <div class="list-group list-group-flush">
                                                    @forelse ($room->beds as $bed)
                                                        <div class="list-group-item d-flex justify-content-between align-items-center px-2 py-1">
                                                            <div>
                                                                <span class="font-monospace fw-bold small tabular-nums">{{ $bed->code }}</span>
                                                                @switch($bed->status)
                                                                    @case('disponible')
                                                                        <span class="badge bg-success ms-1"><i class="bi bi-check me-1" aria-hidden="true"></i>Disponible</span>
                                                                        @break
                                                                    @case('ocupada')
                                                                        <span class="badge bg-danger ms-1"><i class="bi bi-person-fill me-1" aria-hidden="true"></i>Ocupada</span>
                                                                        @break
                                                                    @case('en_limpieza')
                                                                        <span class="badge bg-warning text-dark ms-1"><i class="bi bi-stars me-1" aria-hidden="true"></i>Limpieza</span>
                                                                        @break
                                                                    @case('mantenimiento')
                                                                        <span class="badge bg-secondary ms-1"><i class="bi bi-tools me-1" aria-hidden="true"></i>Mantenimiento</span>
                                                                        @break
                                                                    @case('fuera_de_servicio')
                                                                        <span class="badge bg-dark ms-1"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Fuera de servicio</span>
                                                                        @break
                                                                    @default
                                                                        <span class="badge bg-secondary ms-1">{{ $bed->status }}</span>
                                                                @endswitch
                                                            </div>

                                                            @can('camillas.gestionar')
                                                                <div class="btn-group btn-group-sm">
                                                                    @if ($bed->status === 'ocupada')
                                                                        <form method="POST" action="{{ route('beds.release', $bed) }}">
                                                                            @csrf
                                                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Liberar camilla">
                                                                                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Liberar
                                                                            </button>
                                                                        </form>
                                                                    @elseif ($bed->status === 'disponible')
                                                                        <form method="POST" action="{{ route('beds.update-status', $bed) }}">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="mantenimiento">
                                                                            <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Poner en mantenimiento">
                                                                                <i class="bi bi-wrench" aria-hidden="true"></i>
                                                                            </button>
                                                                        </form>
                                                                    @elseif ($bed->status === 'mantenimiento' || $bed->status === 'en_limpieza')
                                                                        <form method="POST" action="{{ route('beds.update-status', $bed) }}">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="disponible">
                                                                            <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2" title="Habilitar camilla">
                                                                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                                                                            </button>
                                                                        </form>
                                                                    @endif
                                                                </div>
                                                            @endcan
                                                        </div>
                                                    @empty
                                                        <span class="text-muted small p-2 d-block">Sin camillas configuradas</span>
                                                    @endforelse
                                                </div>
                                            </div>

                                            @can('camillas.gestionar')
                                                <div class="card-footer bg-white py-1 px-2 border-top-0">
                                                    <form method="POST" action="{{ route('beds.store', $room) }}" class="d-flex gap-1">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-primary w-100 py-0" {{ $room->beds->count() >= $room->capacity ? 'disabled' : '' }}>
                                                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar Camilla
                                                        </button>
                                                    </form>
                                                </div>
                                            @endcan
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12"><p class="text-muted small">No hay habitaciones registradas en esta área.</p></div>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <p class="text-muted small">No hay áreas configuradas en este piso.</p>
                    @endforelse
                </div>
            @empty
                <p class="text-muted small">No hay pisos registrados en esta sede.</p>
            @endforelse
        </div>
    </div>
@empty
    <div class="alert alert-info">No se han registrado sedes hospitalarias en el sistema.</div>
@endforelse
@endsection
