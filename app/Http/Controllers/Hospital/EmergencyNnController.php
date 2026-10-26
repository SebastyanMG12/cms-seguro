<?php

namespace App\Http\Controllers\Hospital;

use App\Http\Controllers\Controller;
use App\Models\EmergencyEpisode;
use App\Services\IdentityReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmergencyNnController extends Controller
{
    /**
     * Formulario para el registro de emergencia de un paciente sin identificar (NN).
     */
    public function create(): View
    {
        Gate::authorize('createNn', EmergencyEpisode::class);

        $nextNnCode = EmergencyEpisode::generateNnCode();

        return view('episodes.nn.create', [
            'nextNnCode' => $nextNnCode,
        ]);
    }

    /**
     * Guarda el ingreso de emergencia para un paciente sin identificar (NN).
     */
    public function store(Request $request, IdentityReconciliationService $service): RedirectResponse
    {
        Gate::authorize('createNn', EmergencyEpisode::class);

        $validated = $request->validate([
            'apparent_sex' => ['required', 'string', 'in:masculino,femenino,indeterminado'],
            'estimated_age' => ['required', 'string', 'min:2', 'max:60'],
            'distinguishing_marks' => ['required', 'string', 'min:5', 'max:2000'],
            'arrival_mode' => ['required', 'string', 'in:ambulancia,policia,bomberos,transeunte,propio_pie,otro'],
            'brought_by_name' => ['required', 'string', 'min:2', 'max:150'],
            'brought_by_contact' => ['nullable', 'string', 'max:150'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'initial_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $episode = $service->registerEmergencyNn($validated, auth()->user());

        return redirect()->route('episodes.show', $episode)
            ->with('success', "Ingreso de emergencia para paciente NN registrado exitosamente con código {$episode->nn_code}.");
    }

    /**
     * Pantalla de conciliación de identidad para vincular paciente identificado a episodio NN.
     */
    public function showReconcileForm(EmergencyEpisode $episode): View
    {
        Gate::authorize('reconcile', $episode);

        $episode->load(['patient', 'clinicalData', 'originalNnPatient']);

        return view('episodes.reconcile', [
            'episode' => $episode,
        ]);
    }

    /**
     * Procesa la conciliación de identidad del episodio NN.
     */
    public function reconcile(Request $request, EmergencyEpisode $episode, IdentityReconciliationService $service): RedirectResponse
    {
        Gate::authorize('reconcile', $episode);

        $validated = $request->validate([
            'reconciliation_mode' => ['required', 'string', 'in:existing,new'],
            'existing_patient_id' => ['required_if:reconciliation_mode,existing', 'nullable', 'uuid', 'exists:patients,id'],
            'document_type' => ['required_if:reconciliation_mode,new', 'nullable', 'string', 'max:50'],
            'document_number' => ['required_if:reconciliation_mode,new', 'nullable', 'string', 'min:3', 'max:30'],
            'full_name' => ['required_if:reconciliation_mode,new', 'nullable', 'string', 'min:3', 'max:150'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'in:masculino,femenino,indeterminado'],
            'phone' => ['nullable', 'string', 'min:7', 'max:20'],
            'reconciliation_evidence' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $service->reconcileIdentity($episode, $validated, auth()->user());

        return redirect()->route('episodes.show', $episode)
            ->with('success', "Identidad del paciente conciliada exitosamente. El episodio ha sido vinculado a {$episode->fresh()->patient->full_name}.");
    }

    /**
     * Búsqueda por documento para comprobar existencia de paciente previo en la conciliación.
     */
    public function searchPatient(Request $request, IdentityReconciliationService $service): JsonResponse
    {
        Gate::authorize('episodios.conciliar');

        $docType = $request->query('document_type');
        $docNumber = $request->query('document_number');

        if (empty($docType) || empty($docNumber)) {
            return response()->json(['found' => false]);
        }

        $patient = $service->findPatientByDocument($docType, $docNumber);

        if (! $patient || $patient->identity_status !== 'identificado') {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'patient' => [
                'id' => $patient->id,
                'full_name' => $patient->full_name,
                'document_type' => $patient->document_type,
                'document_number' => $patient->document_number,
                'birth_date' => $patient->birth_date?->format('Y-m-d'),
                'gender' => $patient->gender,
                'phone' => $patient->phone,
            ],
        ]);
    }
}
