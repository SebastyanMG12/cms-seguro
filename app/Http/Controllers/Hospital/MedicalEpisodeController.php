<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\EmergencyEpisode;
use App\Models\EpisodeAssignment;
use App\Models\User;
use App\Rules\PersonName;
use App\Services\Audit;
use App\Services\ClinicalService;
use App\Services\EpisodeStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MedicalEpisodeController extends Controller
{
    /**
     * Listado principal de episodios con filtros clínicos y paciente seleccionado resaltado.
     */
    public function index(Request $request, ClinicalService $clinicalService): View
    {
        Gate::authorize('viewAny', EmergencyEpisode::class);

        $user = auth()->user();
        $isMedical = $user->hasAnyRole(['medico', 'enfermero']) || $user->staffProfile !== null;
        $myActivePatientsCount = $clinicalService->countActivePatients($user);

        $defaultTab = ($isMedical && $myActivePatientsCount > 0 && ! $request->has('tab')) ? 'mis_pacientes' : 'activos';
        $tab = $request->query('tab', $defaultTab);
        $selectedId = $request->query('selected');

        $query = EmergencyEpisode::with([
            'patient',
            'responsibleUser.staffProfile',
            'activeBedAssignment.bed.room',
            'activeAssignments.user.staffProfile',
        ]);

        switch ($tab) {
            case 'mis_pacientes':
                $query->whereIn('status', ['en_valoracion', 'ingresado'])
                    ->where(function ($q) use ($user) {
                        $q->where('responsible_user_id', $user->id)
                            ->orWhereHas('activeAssignments', fn ($sub) => $sub->where('user_id', $user->id)->whereNull('ended_at'));
                    });
                break;
            case 'en_espera':
                $query->where('status', 'en_espera');
                break;
            case 'egresados':
                $query->where('status', 'egresado');
                break;
            case 'preregistrados':
                $query->where('status', 'preregistrado');
                break;
            case 'activos':
            default:
                $query->whereIn('status', ['en_valoracion', 'ingresado']);
                break;
        }

        $episodes = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Métricas de cola de urgencias
        $counts = [
            'mis_pacientes' => $myActivePatientsCount,
            'activos' => EmergencyEpisode::whereIn('status', ['en_valoracion', 'ingresado'])->count(),
            'en_espera' => EmergencyEpisode::where('status', 'en_espera')->count(),
            'preregistrados' => EmergencyEpisode::where('status', 'preregistrado')->count(),
            'egresados' => EmergencyEpisode::where('status', 'egresado')->count(),
        ];

        return view('episodes.index', [
            'episodes' => $episodes,
            'tab' => $tab,
            'selectedId' => $selectedId,
            'counts' => $counts,
            'myActivePatientsCount' => $myActivePatientsCount,
        ]);
    }

    /**
     * Vista detallada de la historia clínica del episodio con auditoría de lectura.
     */
    public function show(Request $request, EmergencyEpisode $episode, ClinicalService $clinicalService): View
    {
        Gate::authorize('view', $episode);

        $user = auth()->user();
        $isAssigned = $episode->isAssignedUser($user);
        $isSupervisor = $user->hasAnyRole(['coordinador', 'administrador', 'auditor']);

        // Caso (a): Paciente sin asignar en espera/preregistrado y el profesional asistencial tiene turno activo
        $isUnassignedWaiting = $episode->responsible_user_id === null
            && in_array($episode->status, ['preregistrado', 'en_espera'], true);
        $hasActiveShift = $user->hasActiveShift();

        $canViewDirectly = $isAssigned || $isSupervisor || ($isUnassignedWaiting && $hasActiveShift);

        $exceptionalReason = null;

        // Caso (b): Paciente asignado a otro profesional o fuera de turno asistencial
        if (! $canViewDirectly) {
            $sessionData = session("exceptional_access_{$episode->id}");
            $isSessionValid = false;

            if (is_array($sessionData) && isset($sessionData['expires_at'], $sessionData['reason'])) {
                if (now()->timestamp <= $sessionData['expires_at']) {
                    $isSessionValid = true;
                    $exceptionalReason = $sessionData['reason'];
                } else {
                    session()->forget("exceptional_access_{$episode->id}");
                }
            } elseif (is_string($sessionData) && ! empty($sessionData)) {
                $isSessionValid = true;
                $exceptionalReason = $sessionData;
            }

            if (! $isSessionValid) {
                return view('episodes.break_glass', [
                    'episode' => $episode,
                    'hasActiveShift' => $hasActiveShift,
                ]);
            }
        }

        // Registro obligatorio de auditoría de lectura
        $clinicalService->recordMedicalRecordRead(
            $episode,
            $user,
            (! $isAssigned && ! $isSupervisor && $exceptionalReason !== null) ? $exceptionalReason : null
        );

        $episode->load([
            'patient',
            'clinicalData',
            'companion',
            'clinicalEntries.author.staffProfile',
            'clinicalEntries.clarifications.author',
            'clinicalEntries.annulments.author',
            'assignments.user.staffProfile',
            'activeBedAssignment.bed.room',
            'consents',
        ]);

        // Camillas disponibles para posible asignación si el paciente está ingresado
        $availableBeds = Bed::where('status', 'disponible')
            ->whereHas('room', fn ($q) => $q->where('status', 'disponible'))
            ->get();

        // Personal asistencial activo en turno para asignación
        $availableStaff = User::whereHas('staffProfile', fn ($q) => $q->where('status', 'activo'))
            ->whereHas('shifts', fn ($q) => $q->where('status', 'activo'))
            ->get();

        return view('episodes.show', [
            'episode' => $episode,
            'availableBeds' => $availableBeds,
            'availableStaff' => $availableStaff,
            'exceptionalReason' => (! $isAssigned && ! $isSupervisor) ? $exceptionalReason : null,
            'isAssigned' => $isAssigned,
        ]);
    }

    public function registerArrival(EmergencyEpisode $episode, EpisodeStateMachine $stateMachine): RedirectResponse
    {
        Gate::authorize('registerArrival', $episode);

        $stateMachine->registerArrival($episode);

        return redirect()->route('episodes.show', $episode)
            ->with('success', 'Llegada del paciente registrada exitosamente en sala de espera.');
    }

    public function assignTriage(Request $request, EmergencyEpisode $episode, EpisodeStateMachine $stateMachine): RedirectResponse
    {
        Gate::authorize('assignTriage', $episode);

        $validated = $request->validate([
            'triage_level' => ['required', 'string', 'in:I,II,III,IV,V'],
        ]);

        $stateMachine->assignTriage($episode, $validated['triage_level'], auth()->user());

        return redirect()->route('episodes.show', $episode)
            ->with('success', "Nivel de Triage {$validated['triage_level']} asignado exitosamente.");
    }

    public function confirmAdmission(EmergencyEpisode $episode, EpisodeStateMachine $stateMachine): RedirectResponse
    {
        Gate::authorize('confirmAdmission', $episode);

        $stateMachine->confirmAdmission($episode);

        return redirect()->route('episodes.show', $episode)
            ->with('success', 'Ingreso médico del paciente confirmado.');
    }

    public function discharge(Request $request, EmergencyEpisode $episode, EpisodeStateMachine $stateMachine): RedirectResponse
    {
        Gate::authorize('discharge', $episode);

        $validated = $request->validate([
            'discharge_type' => ['required', 'string', 'in:alta_medica,remision,alta_voluntaria,abandono,fallecimiento'],
        ]);

        $stateMachine->discharge($episode, $validated['discharge_type']);

        return redirect()->route('episodes.show', $episode)
            ->with('success', 'Egreso del paciente registrado formalmente. Episodio cerrado.');
    }

    public function addClinicalEntry(Request $request, EmergencyEpisode $episode, ClinicalService $clinicalService): RedirectResponse
    {
        Gate::authorize('addClinicalEntry', $episode);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:diagnostico,procedimiento,nota,informacion_acompanante,aclaracion,anulacion'],
            'content' => ['required', 'string', 'min:3', 'max:5000'],
            'references_entry_id' => ['nullable', 'integer', 'exists:clinical_entries,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $clinicalService->addClinicalEntry(
            $episode,
            auth()->user(),
            $validated['type'],
            $validated['content'],
            $validated['references_entry_id'] ?? null,
            $validated['reason'] ?? null
        );

        return redirect()->route('episodes.show', $episode)
            ->with('success', 'Entrada clínica inmutable registrada en la historia.');
    }

    public function assignStaff(Request $request, EmergencyEpisode $episode, ClinicalService $clinicalService): RedirectResponse
    {
        Gate::authorize('assignStaff', $episode);

        $validated = $request->validate([
            'staff_user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string', 'in:responsable,apoyo'],
        ]);

        $staffUser = User::findOrFail($validated['staff_user_id']);
        $clinicalService->assignStaff($episode, $staffUser, $validated['role']);

        return redirect()->route('episodes.show', $episode)
            ->with('success', "Personal asistencial asignado en calidad de {$validated['role']}.");
    }

    public function endStaffAssignment(Request $request, EmergencyEpisode $episode, EpisodeAssignment $assignment, ClinicalService $clinicalService): RedirectResponse
    {
        Gate::authorize('endStaffAssignment', [$episode, $assignment]);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $clinicalService->endStaffAssignment($episode, $assignment, auth()->user(), $validated['reason'] ?? null);

        return redirect()->route('episodes.show', $episode)
            ->with('success', 'Participación de apoyo asistencial finalizada exitosamente.');
    }

    public function assignBed(Request $request, EmergencyEpisode $episode, ClinicalService $clinicalService): RedirectResponse
    {
        Gate::authorize('assignBed', $episode);

        $validated = $request->validate([
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
        ]);

        $bed = Bed::findOrFail($validated['bed_id']);
        $clinicalService->assignBedToEpisode($episode, $bed);

        return redirect()->route('episodes.show', $episode)
            ->with('success', "Camilla {$bed->code} asignada exitosamente al paciente.");
    }

    public function updateCompanion(Request $request, EmergencyEpisode $episode, ClinicalService $clinicalService): RedirectResponse
    {
        Gate::authorize('updateCompanion', $episode);

        $validated = $request->validate([
            'name' => ['required', 'string', new PersonName(2, 150)],
            'phone' => ['nullable', 'string', 'min:7', 'max:20'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $clinicalService->updateCompanion($episode, $validated);

        return redirect()->route('episodes.show', $episode)
            ->with('success', 'Datos del acompañante actualizados.');
    }

    /**
     * Procesa la solicitud controlada de acceso excepcional (Break-Glass) a una historia clínica.
     * Exige rol clínico, turno asistencial activo, causal tipificada, justificación no trivial,
     * límite de tasa (5/hora) y genera alerta forense inmediata, otorgando sesión temporal por 30 minutos.
     */
    public function requestBreakGlass(Request $request, EmergencyEpisode $episode): RedirectResponse
    {
        Gate::authorize('view', $episode);

        $user = $request->user();

        // 1. Restringido a personal asistencial (médico o enfermero)
        if (! $user->hasAnyRole(['medico', 'enfermero'])) {
            abort(403, 'El acceso excepcional sólo está disponible para el personal asistencial de urgencias.');
        }

        // 2. Requiere turno asistencial activo
        if (! $user->hasActiveShift()) {
            return back()->withErrors([
                'shift' => 'No puede solicitar acceso excepcional sin contar con un turno asistencial activo registrado.',
            ]);
        }

        // 3. Límite de frecuencia: máximo 5 solicitudes por hora por usuario
        $rateLimitKey = "break_glass:{$user->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            return back()->withErrors([
                'rate_limit' => "Ha alcanzado el límite de 5 solicitudes de acceso excepcional por hora. Intente nuevamente en {$seconds} segundos o contacte a la coordinación médica.",
            ]);
        }

        // 4. Validación de motivo: categoría cerrada + justificación descriptiva
        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in([
                'emergencia_vital',
                'interconsulta_urgente',
                'relevo_turno',
                'ausencia_tratante',
                'otro',
            ])],
            'exceptional_reason' => ['required', 'string', 'min:15', 'max:500'],
        ]);

        $reason = trim($validated['exceptional_reason']);

        // Rechazar justificaciones triviales o secuencias repetitivas
        if (preg_match('/(.)\1{4,}/u', $reason) || preg_match('/^[0-9\s\.\-]+$/', $reason)) {
            return back()->withErrors([
                'exceptional_reason' => 'Debe ingresar una justificación clínica descriptiva válida. No se admiten caracteres repetitivos o triviales.',
            ]);
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $categoryLabels = [
            'emergencia_vital' => 'Emergencia Vital / Reanimación',
            'interconsulta_urgente' => 'Interconsulta Médica Urgente',
            'relevo_turno' => 'Relevo Imprevisto de Turno',
            'ausencia_tratante' => 'Ausencia Temporal del Médico Tratante',
            'otro' => 'Otro Motivo Asistencial',
        ];

        $categoryLabel = $categoryLabels[$validated['category']] ?? $validated['category'];
        $formattedReason = "[{$categoryLabel}] {$reason}";

        // Concesión de acceso temporal por 30 minutos (solo lectura)
        session()->put("exceptional_access_{$episode->id}", [
            'category' => $validated['category'],
            'reason' => $formattedReason,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);

        // Alerta inmediata de auditoría para coordinación médica y auditor
        Audit::log('episodio.break_glass', $episode, [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'category' => $validated['category'],
            'reason' => $formattedReason,
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
            'alert_level' => 'CRITICAL_CLINICAL_BREAK_GLASS',
            'patient_id' => $episode->patient_id,
        ]);

        return redirect()->route('episodes.show', $episode)
            ->with('success', 'Acceso excepcional concedido por 30 minutos bajo supervisión de auditoría.');
    }
}
