@extends('layouts.app')

@section('title', 'Auditoría Forense e Inmutable - Hospital de Ubaté')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-0 fw-bold">
            <i class="bi bi-shield-check text-primary me-2" aria-hidden="true"></i>Bitácora de Auditoría Forense
        </h1>
        <small class="text-muted">Registro inmutable de trazabilidad médica y de seguridad (Ley 1581 de 2012 / Res. 1995 de 1999)</small>
    </div>
    <div>
        <a href="{{ route('audit.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1" aria-hidden="true"></i>Exportar CSV
        </a>
    </div>
</div>

{{-- Banner de Inmutabilidad Legal --}}
<div class="alert alert-secondary border d-flex align-items-center mb-3 py-2 px-3 shadow-sm bg-light text-dark" role="alert">
    <i class="bi bi-lock-fill fs-4 me-2 text-primary" aria-hidden="true"></i>
    <div class="small">
        <strong>Aviso de Custodia Legal:</strong> Los registros de auditoría de este sistema son de <strong>solo inserción</strong> y estrictamente inmutables. Ningún usuario, administrador o auditor tiene privilegios para modificar o eliminar estos eventos.
    </div>
</div>

{{-- Pestañas de Navegación por Tipo de Consulta --}}
<ul class="nav nav-pills mb-3 gap-2">
    <li class="nav-item">
        <a class="nav-link {{ $viewMode !== 'clinical' ? 'active' : '' }} py-1 px-3" href="{{ route('audit.index', ['view_mode' => 'all']) }}">
            <i class="bi bi-list-ul me-1" aria-hidden="true"></i>Todos los Eventos
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $viewMode === 'clinical' ? 'active bg-primary' : '' }} py-1 px-3" href="{{ route('audit.index', ['view_mode' => 'clinical']) }}">
            <i class="bi bi-file-earmark-medical me-1" aria-hidden="true"></i>Lecturas de Historia Clínica y Break-Glass
        </a>
    </li>
</ul>

{{-- Filtros Avanzados --}}
<div class="card shadow-sm border-0 mb-3 bg-white">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('audit.index') }}" class="row g-2 align-items-end">
            <input type="hidden" name="view_mode" value="{{ $viewMode }}">

            <div class="col-md-3 col-sm-6">
                <label for="event" class="form-label small fw-semibold text-muted mb-1">Evento</label>
                <select name="event" id="event" class="form-select form-select-sm">
                    <option value="">-- Todos los eventos --</option>
                    @foreach ($eventsList as $event)
                        <option value="{{ $event }}" {{ request('event') === $event ? 'selected' : '' }}>{{ $event }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 col-sm-6">
                <label for="user_id" class="form-label small fw-semibold text-muted mb-1">Usuario / Actor</label>
                <select name="user_id" id="user_id" class="form-select form-select-sm">
                    <option value="">-- Todos los usuarios --</option>
                    @foreach ($usersList as $user)
                        <option value="{{ $user->id }}" {{ (string) request('user_id') === (string) $user->id ? 'selected' : '' }}>
                            {{ $user->name }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 col-sm-4">
                <label for="episode_code" class="form-label small fw-semibold text-muted mb-1">Código Episodio</label>
                <input type="text" name="episode_code" id="episode_code" value="{{ request('episode_code') }}" class="form-control form-control-sm font-monospace text-uppercase" placeholder="Ej: UBT1A2B3">
            </div>

            <div class="col-md-2 col-sm-4">
                <label for="date_from" class="form-label small fw-semibold text-muted mb-1">Fecha Desde</label>
                <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
            </div>

            <div class="col-md-2 col-sm-4">
                <label for="date_to" class="form-label small fw-semibold text-muted mb-1">Fecha Hasta</label>
                <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                <a href="{{ route('audit.index', ['view_mode' => $viewMode]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Limpiar
                </a>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-filter me-1" aria-hidden="true"></i>Aplicar Filtros
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Tabla de Registros de Auditoría --}}
<div class="card shadow-sm border-0 bg-white">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 table-compact">
                <thead class="table-light">
                    <tr>
                        <th style="width: 14%;">Fecha y Hora</th>
                        <th style="width: 18%;">Usuario / Rol</th>
                        <th style="width: 16%;">Evento</th>
                        <th style="width: 12%;">Episodio</th>
                        <th style="width: 12%;">IP / Red</th>
                        <th>Metadatos y Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="font-monospace tabular-nums small text-nowrap">
                                {{ $log->created_at->format('d/m/Y H:i:s') }}
                            </td>
                            <td>
                                @if ($log->user)
                                    <div class="fw-semibold text-dark">{{ $log->user->name }}</div>
                                    <small class="text-muted">{{ $log->user->email }}</small>
                                    @if ($log->user->roles->count() > 0)
                                        <div class="mt-1">
                                            @foreach ($log->user->roles as $role)
                                                <span class="badge bg-secondary-subtle text-secondary border py-0 px-1" style="font-size: 0.65rem;">{{ $role->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted fst-italic">Sistema / Anónimo</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $badgeClass = 'bg-secondary';
                                    if (str_starts_with($log->event, 'episodio.break_glass')) {
                                        $badgeClass = 'bg-danger text-white';
                                    } elseif (str_starts_with($log->event, 'episodio.pdf')) {
                                        $badgeClass = 'bg-primary text-white';
                                    } elseif (str_starts_with($log->event, 'episodio.')) {
                                        $badgeClass = 'bg-info text-dark';
                                    } elseif (str_starts_with($log->event, 'login') || str_starts_with($log->event, 'two_factor')) {
                                        $badgeClass = 'bg-dark text-white';
                                    } elseif (str_starts_with($log->event, 'acompanante.')) {
                                        $badgeClass = 'bg-success text-white';
                                    }
                                @endphp
                                <span class="badge {{ $badgeClass }} font-monospace" style="font-size: 0.72rem;">
                                    {{ $log->event }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $publicCode = $log->metadata['public_code'] ?? $log->metadata['episode_code'] ?? null;
                                @endphp
                                @if ($publicCode)
                                    <span class="badge bg-light text-dark border font-monospace">
                                        <i class="bi bi-file-earmark-medical me-1 text-primary"></i>{{ $publicCode }}
                                    </span>
                                @elseif ($log->model_type)
                                    <span class="small text-muted font-monospace">{{ $log->model_type }} #{{ $log->model_id }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="font-monospace small text-muted text-nowrap">
                                <i class="bi bi-hdd-network me-1"></i>{{ $log->ip_address ?? '127.0.0.1' }}
                            </td>
                            <td class="small">
                                @if ($log->metadata)
                                    <div class="text-dark">
                                        @foreach ($log->metadata as $key => $val)
                                            @if ($key !== 'public_code' && $key !== 'episode_code')
                                                <span class="badge bg-light text-secondary border me-1 mb-1">
                                                    <strong>{{ $key }}:</strong> {{ is_array($val) ? json_encode($val) : $val }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted fst-italic">Sin metadatos adicionales</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-search fs-3 d-block text-secondary mb-2" aria-hidden="true"></i>
                                No se encontraron registros de auditoría que coincidan con los criterios seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($logs->hasPages())
        <div class="card-footer bg-white border-top py-2 px-3">
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection