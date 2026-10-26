<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * Muestra la bitácora inmutable de auditoría con filtros avanzados y vista de lecturas clínicas.
     */
    public function index(Request $request): View|StreamedResponse
    {
        Gate::authorize('auditoria.ver');

        $validated = $request->validate([
            'event' => ['nullable', 'string', 'max:60'],
            'user_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'episode_code' => ['nullable', 'string', 'max:20'],
            'view_mode' => ['nullable', 'string', 'in:all,clinical'],
            'export' => ['nullable', 'string', 'in:csv'],
        ]);

        $viewMode = $validated['view_mode'] ?? 'all';

        $query = AuditLog::with('user.roles')
            ->when($viewMode === 'clinical', function ($q) {
                $q->whereIn('event', [
                    'episodio.lectura',
                    'episodio.break_glass',
                    'episodio.pdf_generado',
                    'historia_clinica.consultada',
                ]);
            })
            ->when(! empty($validated['event']), function ($q) use ($validated) {
                $q->where('event', $validated['event']);
            })
            ->when(! empty($validated['user_id']), function ($q) use ($validated) {
                $q->where('user_id', $validated['user_id']);
            })
            ->when(! empty($validated['date_from']), function ($q) use ($validated) {
                $q->where('created_at', '>=', Carbon::parse($validated['date_from'])->startOfDay());
            })
            ->when(! empty($validated['date_to']), function ($q) use ($validated) {
                $q->where('created_at', '<=', Carbon::parse($validated['date_to'])->endOfDay());
            })
            ->when(! empty($validated['episode_code']), function ($q) use ($validated) {
                $code = strtoupper(trim($validated['episode_code']));
                $q->where(function ($sub) use ($code) {
                    $sub->where('metadata->public_code', $code)
                        ->orWhere('metadata->episode_code', $code);
                });
            })
            ->latest('created_at');

        // Exportación a CSV
        if (($validated['export'] ?? null) === 'csv') {
            return $this->exportCsv($query);
        }

        $logs = $query->paginate(25)->withQueryString();

        // Lista de eventos distintos y usuarios para los selectores de filtro
        $eventsList = AuditLog::select('event')->distinct()->orderBy('event')->pluck('event');
        $usersList = User::select('id', 'name', 'email')->orderBy('name')->get();

        return view('audit.index', [
            'logs' => $logs,
            'viewMode' => $viewMode,
            'eventsList' => $eventsList,
            'usersList' => $usersList,
        ]);
    }

    /**
     * Exporta los registros de auditoría filtrados en formato CSV con cabeceras de seguridad.
     */
    private function exportCsv($query): StreamedResponse
    {
        $filename = 'auditoria-forense-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // BOM para compatibilidad con Excel en UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados de columna
            fputcsv($handle, [
                'ID',
                'Fecha y Hora (UTC)',
                'Usuario ID',
                'Nombre Usuario',
                'Email Usuario',
                'Roles en Momento',
                'Evento',
                'Modelo',
                'ID Modelo',
                'Dirección IP',
                'Agente de Usuario',
                'Metadatos (JSON)',
            ]);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->toIso8601String(),
                        $log->user_id,
                        $log->user?->name ?? 'Sistema',
                        $log->user?->email ?? '-',
                        $log->user ? $log->user->roles->pluck('name')->implode(', ') : 'N/A',
                        $log->event,
                        $log->model_type,
                        $log->model_id,
                        $log->ip_address,
                        $log->user_agent,
                        $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_UNICODE) : '',
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
