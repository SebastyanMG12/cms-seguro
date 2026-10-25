@extends('layouts.app')

@section('title', 'Censo y Ocupación Hospitalaria')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Censo y Ocupación Hospitalaria</h1>
        <p class="text-muted mb-0">Monitoreo en tiempo real de áreas, habitaciones y disponibilidad de camillas.</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Tarjetas de métricas consolidadas --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 text-center p-3 bg-white">
            <span class="text-muted small fw-bold text-uppercase">Total Camillas</span>
            <span class="h3 mb-0 fw-bold text-dark">{{ $report['total_beds'] }}</span>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 text-center p-3 bg-white">
            <span class="text-muted small fw-bold text-uppercase">Disponibles</span>
            <span class="h3 mb-0 fw-bold text-success">{{ $report['beds_by_status']['disponible'] ?? 0 }}</span>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 text-center p-3 bg-white">
            <span class="text-muted small fw-bold text-uppercase">Ocupadas</span>
            <span class="h3 mb-0 fw-bold text-danger">{{ $report['beds_by_status']['ocupada'] ?? 0 }}</span>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 text-center p-3 bg-white">
            <span class="text-muted small fw-bold text-uppercase">En Limpieza</span>
            <span class="h3 mb-0 fw-bold text-warning">{{ $report['beds_by_status']['en_limpieza'] ?? 0 }}</span>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 text-center p-3 bg-white">
            <span class="text-muted small fw-bold text-uppercase">Mantenimiento</span>
            <span class="h3 mb-0 fw-bold text-secondary">{{ $report['beds_by_status']['mantenimiento'] ?? 0 }}</span>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card shadow-sm border-0 text-center p-3 bg-white">
            <span class="text-muted small fw-bold text-uppercase">Habitaciones Llenas</span>
            <span class="h3 mb-0 fw-bold text-primary">{{ $report['full_rooms'] }} / {{ $report['total_rooms'] }}</span>
        </div>
    </div>
</div>

{{-- Detalle por Sedes, Pisos y Habitaciones --}}
@forelse ($sites as $site)
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0 fw-bold">{{ $site->name }} ({{ $site->code }})</h2>
            <span class="badge {{ $site->isActive() ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($site->status) }}</span>
        </div>
        <div class="card-body">
            @forelse ($site->floors as $floor)
                <div class="mb-4">
                    <h3 class="h6 text-uppercase fw-bold text-secondary border-bottom pb-2">
                        Piso {{ $floor->number }} - {{ $floor->name }}
                    </h3>

                    @forelse ($floor->areas as $area)
                        <div class="ms-md-3 mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <h4 class="h6 mb-0 fw-bold text-primary">{{ $area->name }}</h4>
                                <span class="badge bg-light text-dark border ms-2 small">{{ ucfirst($area->type) }}</span>
                            </div>

                            <div class="row g-3">
                                @forelse ($area->rooms as $room)
                                    <div class="col-md-6 col-xl-4">
                                        <div class="card h-100 border {{ $room->isFull() ? 'border-danger' : 'border-light-subtle' }}">
                                            <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                                                <div>
                                                    <strong>Hab. {{ $room->code }}</strong>
                                                    <small class="text-muted">({{ $room->beds->count() }}/{{ $room->capacity }} camillas)</small>
                                                </div>
                                                <div>
                                                    @if ($room->isFull())
                                                        <span class="badge bg-danger">Llena</span>
                                                    @endif
                                                    @if ($room->is_isolation)
                                                        <span class="badge bg-info text-dark">Aislamiento</span>
                                                    @endif
                                                    @if ($room->is_pediatric)
                                                        <span class="badge bg-primary">Pediátrica</span>
                                                    @endif
                                                    @if ($room->status !== 'disponible')
                                                        <span class="badge bg-secondary">{{ ucfirst($room->status) }}</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="card-body p-2">
                                                <div class="list-group list-group-flush">
                                                    @forelse ($room->beds as $bed)
                                                        <div class="list-group-item d-flex justify-content-between align-items-center px-1 py-2">
                                                            <div>
                                                                <span class="font-monospace fw-bold">{{ $bed->code }}</span>
                                                                @switch($bed->status)
                                                                    @case('disponible')
                                                                        <span class="badge bg-success ms-1">Disponible</span>
                                                                        @break
                                                                    @case('ocupada')
                                                                        <span class="badge bg-danger ms-1">Ocupada</span>
                                                                        @break
                                                                    @case('en_limpieza')
                                                                        <span class="badge bg-warning text-dark ms-1">Limpieza</span>
                                                                        @break
                                                                    @case('mantenimiento')
                                                                        <span class="badge bg-secondary ms-1">Mantenimiento</span>
                                                                        @break
                                                                    @case('fuera_de_servicio')
                                                                        <span class="badge bg-dark ms-1">Fuera de servicio</span>
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
                                                                            <button type="submit" class="btn btn-outline-warning btn-sm py-0">Liberar</button>
                                                                        </form>
                                                                    @elseif ($bed->status === 'en_limpieza')
                                                                        <form method="POST" action="{{ route('beds.update-status', $bed) }}">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="disponible">
                                                                            <button type="submit" class="btn btn-outline-success btn-sm py-0">Lista</button>
                                                                        </form>
                                                                    @elseif ($bed->status === 'disponible')
                                                                        <form method="POST" action="{{ route('beds.update-status', $bed) }}">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="mantenimiento">
                                                                            <button type="submit" class="btn btn-outline-secondary btn-sm py-0">Mant.</button>
                                                                        </form>
                                                                    @else
                                                                        <form method="POST" action="{{ route('beds.update-status', $bed) }}">
                                                                            @csrf
                                                                            <input type="hidden" name="status" value="disponible">
                                                                            <button type="submit" class="btn btn-outline-success btn-sm py-0">Habilitar</button>
                                                                        </form>
                                                                    @endif
                                                                </div>
                                                            @endcan
                                                        </div>
                                                    @empty
                                                        <div class="text-muted small p-2">Sin camillas registradas.</div>
                                                    @endforelse
                                                </div>

                                                @can('camillas.gestionar')
                                                    @if ($room->beds->count() < $room->capacity && $room->isAvailable())
                                                        <form method="POST" action="{{ route('rooms.beds.store', $room) }}" class="mt-2 text-center">
                                                            @csrf
                                                            <button type="submit" class="btn btn-outline-primary btn-sm w-100">+ Agregar camilla</button>
                                                        </form>
                                                    @endif
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-12 text-muted small">Sin habitaciones registradas en esta área.</div>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small">Sin áreas registradas en este piso.</div>
                    @endforelse
                </div>
            @empty
                <div class="text-muted">Sin pisos registrados en esta sede.</div>
            @endforelse
        </div>
    </div>
@empty
    <div class="alert alert-info">No hay sedes hospitalarias configuradas en el sistema.</div>
@endforelse
@endsection
