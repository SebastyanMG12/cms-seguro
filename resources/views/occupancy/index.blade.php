@extends('layouts.app')

@section('title', 'Censo y Ocupación Hospitalaria')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}" class="text-decoration-none">Administración</a></li>
                <li class="breadcrumb-item active" aria-current="page">Censo y Ocupación</li>
            </ol>
        </nav>
        <h1 class="h4 mb-0 fw-bold text-dark">
            <i class="bi bi-hospital text-primary me-2" aria-hidden="true"></i>Censo y Ocupación Hospitalaria
        </h1>
        <p class="text-muted small mb-0">Monitoreo en tiempo real de sedes, salas de urgencias, estado operativo y gestión de camillas.</p>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2 px-3 small d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
        <div>{{ session('success') }}</div>
        <button type="button" class="btn-close py-2 px-3" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show py-2 px-3 small" role="alert">
        <div class="fw-bold mb-1 d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>No se pudo procesar la acción:
        </div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close py-2 px-3" data-bs-dismiss="alert" aria-label="Cerrar"></button>
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
                <i class="bi bi-person-fill text-danger me-1" aria-hidden="true"></i>Ocupadas
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
                <i class="bi bi-slash-circle text-dark me-1" aria-hidden="true"></i>Fuera Serv.
            </span>
            <span class="h4 mb-0 fw-bold text-dark tabular-nums">{{ $report['beds_by_status']['fuera_de_servicio'] ?? 0 }}</span>
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
                                            {{-- Encabezado de la habitación con estado y gestión --}}
                                            <div class="card-header py-2 px-2 d-flex justify-content-between align-items-center bg-light">
                                                <div>
                                                    <strong class="small d-block text-dark">Hab. {{ $room->code }}</strong>
                                                    <small class="text-muted tabular-nums">({{ $room->beds->count() }}/{{ $room->capacity }} camillas)</small>
                                                </div>
                                                <div class="d-flex align-items-center gap-1">
                                                    @if ($room->status === 'disponible')
                                                        <span class="badge bg-success-subtle text-success border border-success small"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>Disponible</span>
                                                    @elseif ($room->status === 'fuera_de_servicio')
                                                        <span class="badge bg-dark text-white small"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Inhabilitada</span>
                                                    @else
                                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning small"><i class="bi bi-tools me-1" aria-hidden="true"></i>Mantenimiento</span>
                                                    @endif

                                                    @if ($room->is_isolation)
                                                        <span class="badge bg-info text-dark small" title="Sala de aislamiento">Ais</span>
                                                    @endif
                                                    @if ($room->is_pediatric)
                                                        <span class="badge bg-primary small" title="Sala pediátrica">Ped</span>
                                                    @endif

                                                    @can('habitaciones.gestionar')
                                                        @if ($room->status !== 'disponible')
                                                            <form method="POST" action="{{ route('rooms.update-status', $room) }}" class="d-inline">
                                                                @csrf
                                                                <input type="hidden" name="status" value="disponible">
                                                                <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2" title="Habilitar habitación y sus camillas">
                                                                    <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Habilitar
                                                                </button>
                                                            </form>
                                                        @endif

                                                        <div class="dropdown">
                                                            <button class="btn btn-outline-secondary btn-sm py-0 px-2 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Gestionar habitación {{ $room->code }}">
                                                                <i class="bi bi-gear me-1" aria-hidden="true"></i>Gestionar
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                                                                @if ($room->status === 'disponible')
                                                                    <li>
                                                                        <form method="POST" action="{{ route('rooms.update-status', $room) }}" onsubmit="return confirm('¿Inhabilitar la habitación {{ $room->code }}? Sus camillas disponibles quedarán fuera de servicio.');">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="fuera_de_servicio">
                                                                            <button type="submit" class="dropdown-item text-danger">
                                                                                <i class="bi bi-slash-circle me-2 text-danger" aria-hidden="true"></i>Inhabilitar habitación
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                    <li>
                                                                        <form method="POST" action="{{ route('rooms.update-status', $room) }}" onsubmit="return confirm('¿Poner la habitación {{ $room->code }} en mantenimiento? Sus camillas quedarán en mantenimiento.');">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="mantenimiento">
                                                                            <button type="submit" class="dropdown-item text-warning">
                                                                                <i class="bi bi-tools me-2 text-warning" aria-hidden="true"></i>Poner en mantenimiento
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                @else
                                                                    <li>
                                                                        <form method="POST" action="{{ route('rooms.update-status', $room) }}">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="disponible">
                                                                            <button type="submit" class="dropdown-item text-success">
                                                                                <i class="bi bi-check-circle me-2 text-success" aria-hidden="true"></i>Habilitar habitación y camillas
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                @endif
                                                                <li><hr class="dropdown-divider my-1"></li>
                                                                <li>
                                                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalCapacity{{ $room->id }}">
                                                                        <i class="bi bi-sliders me-2 text-primary" aria-hidden="true"></i>Modificar capacidad
                                                                    </button>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    @endcan
                                                </div>
                                            </div>

                                            {{-- Lista de Camillas de la Habitación --}}
                                            <div class="card-body p-1">
                                                <div class="list-group list-group-flush">
                                                    @forelse ($room->beds as $bed)
                                                        <div class="list-group-item d-flex justify-content-between align-items-center px-2 py-1">
                                                            <div>
                                                                <span class="font-monospace fw-bold small tabular-nums">{{ $bed->code }}</span>
                                                                @switch($bed->status)
                                                                    @case('disponible')
                                                                        <span class="badge bg-success-subtle text-success border border-success ms-1"><i class="bi bi-check me-1" aria-hidden="true"></i>Disponible</span>
                                                                        @break
                                                                    @case('ocupada')
                                                                        <span class="badge bg-danger-subtle text-danger border border-danger ms-1"><i class="bi bi-person-fill me-1" aria-hidden="true"></i>Ocupada</span>
                                                                        @break
                                                                    @case('en_limpieza')
                                                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning ms-1"><i class="bi bi-stars me-1" aria-hidden="true"></i>Limpieza</span>
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
                                                                        <form method="POST" action="{{ route('beds.release', $bed) }}" class="d-inline">
                                                                            @csrf
                                                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" title="Liberar camilla">
                                                                                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Liberar
                                                                            </button>
                                                                        </form>
                                                                    @elseif ($bed->status === 'disponible')
                                                                        <form method="POST" action="{{ route('beds.update-status', $bed) }}" class="d-inline">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="mantenimiento">
                                                                            <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2" title="Poner en mantenimiento">
                                                                                <i class="bi bi-wrench" aria-hidden="true"></i>
                                                                            </button>
                                                                        </form>
                                                                        <form method="POST" action="{{ route('beds.destroy', $bed) }}" class="d-inline" onsubmit="return confirm('¿Eliminar la camilla {{ $bed->code }}? Se liberará un cupo en la habitación.');">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2 ms-1" title="Eliminar camilla">
                                                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                                                            </button>
                                                                        </form>
                                                                    @elseif ($bed->status === 'mantenimiento' || $bed->status === 'en_limpieza' || $bed->status === 'fuera_de_servicio')
                                                                        <form method="POST" action="{{ route('beds.update-status', $bed) }}" class="d-inline">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="disponible">
                                                                            <button type="submit" class="btn btn-outline-success btn-sm py-0 px-2" title="Habilitar camilla">
                                                                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                                                                            </button>
                                                                        </form>
                                                                        <form method="POST" action="{{ route('beds.destroy', $bed) }}" class="d-inline" onsubmit="return confirm('¿Eliminar la camilla {{ $bed->code }}? Se liberará un cupo en la habitación.');">
                                                                            @csrf
                                                                            @method('DELETE')
                                                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2 ms-1" title="Eliminar camilla">
                                                                                <i class="bi bi-trash" aria-hidden="true"></i>
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

                                            {{-- Pie de tarjeta: Agregar Camilla o Ampliar Capacidad --}}
                                            @can('camillas.gestionar')
                                                <div class="card-footer bg-white py-1 px-2 border-top-0">
                                                    @if ($room->status !== 'disponible')
                                                        <span class="badge bg-light text-secondary border d-block py-1">
                                                            <i class="bi bi-lock me-1" aria-hidden="true"></i>Habitación inhabilitada
                                                        </span>
                                                    @elseif ($room->beds->count() < $room->capacity)
                                                        <form method="POST" action="{{ route('beds.store', $room) }}" class="d-flex gap-1">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-primary w-100 py-1">
                                                                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar Camilla ({{ $room->beds->count() }}/{{ $room->capacity }})
                                                            </button>
                                                        </form>
                                                    @else
                                                        <div class="d-flex justify-content-between align-items-center bg-light p-1 rounded border">
                                                            <span class="small text-muted">
                                                                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Capacidad llena ({{ $room->capacity }})
                                                            </span>
                                                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" data-bs-toggle="modal" data-bs-target="#modalCapacity{{ $room->id }}" title="Ampliar capacidad de la habitación">
                                                                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Ampliar cupo
                                                            </button>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endcan
                                        </div>
                                    </div>

                                    {{-- Modal para Modificar Capacidad de la Habitación --}}
                                    @can('habitaciones.gestionar')
                                        <div class="modal fade" id="modalCapacity{{ $room->id }}" tabindex="-1" aria-labelledby="modalCapacityLabel{{ $room->id }}" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-sm">
                                                <div class="modal-content">
                                                    <div class="modal-header py-2 px-3">
                                                        <h5 class="modal-title h6 fw-bold text-dark" id="modalCapacityLabel{{ $room->id }}">
                                                            <i class="bi bi-sliders me-1 text-primary" aria-hidden="true"></i>Capacidad: Hab. {{ $room->code }}
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                    </div>
                                                    <form method="POST" action="{{ route('rooms.update', $room) }}">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="modal-body p-3">
                                                            <label for="capacity_{{ $room->id }}" class="form-label small fw-semibold">Número de Camillas Permitidas</label>
                                                            <input type="number" class="form-control form-control-sm tabular-nums" id="capacity_{{ $room->id }}" name="capacity" value="{{ $room->capacity }}" min="{{ $room->beds->count() }}" max="20" required>
                                                            <div class="form-text small">Camillas actuales: {{ $room->beds->count() }}. Mínimo permitido: {{ $room->beds->count() }}.</div>
                                                        </div>
                                                        <div class="modal-footer py-1 px-3">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-primary btn-sm">Guardar Capacidad</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endcan
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
