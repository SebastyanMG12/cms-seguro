<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\EmergencyEpisode;
use App\Models\User;
use App\Services\ClinicalService;
use App\Services\EpisodeStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

        $exceptionalReason = $request->query('exceptional_reason') ?? session("exceptional_access_{$episode->id}");

        // Si el usuario no está asignado ni es supervisor institucional, debe justificar acceso excepcional (Break-Glass)
        if (! $isAssigned && ! $isSupervisor) {
            if (empty($exceptionalReason) || mb_strlen(trim($exceptionalReason)) < 10) {
                return view('episodes.break_glass', [
                    'episode' => $episode,
                ]);
            }

            session()->put("exceptional_access_{$episode->id}", $exceptionalReason);
        }

        // Registro obligatorio de auditoría de lectura
        $clinicalService->recordMedicalRecordRead(
            $episode,
            $user,
            (! $isAssigned && ! $isSupervisor) ? $exceptionalReason : null
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
            'type' => ['required', 'string', 'in:diagnostico,procedimiento,nota,aclaracion,anulacion'],
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
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['nullable', 'string', 'min:7', 'max:20'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $clinicalService->updateCompanion($episode, $validated);

        return redirect()->route('episodes.show', $episode)
            ->with('success', 'Datos del acompañante actualizados.');
    }
}
