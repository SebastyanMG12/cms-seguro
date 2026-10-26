<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Bed;
use App\Models\EmergencyEpisode;
use App\Models\Floor;
use App\Models\Patient;
use App\Models\Room;
use App\Models\Shift;
use App\Models\Site;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\ClinicalService;
use App\Services\EpisodeStateMachine;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class MedicalEpisodeTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected User $nurse;

    protected User $admissions;

    protected User $coordinator;

    protected User $auditor;

    protected Bed $bed;

    protected EmergencyEpisode $episode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Crear usuarios con roles asistenciales
        $this->doctor = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->doctor->assignRole('medico');
        StaffProfile::create([
            'user_id' => $this->doctor->id,
            'code' => 'MED-100001',
            'specialty' => 'Urgenciólogo',
            'status' => 'activo',
        ]);
        Shift::create([
            'user_id' => $this->doctor->id,
            'type' => 'rotativo',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->addHours(6),
            'status' => 'activo',
        ]);

        $this->nurse = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->nurse->assignRole('enfermero');
        StaffProfile::create([
            'user_id' => $this->nurse->id,
            'code' => 'ENF-200001',
            'specialty' => 'Enfermera de Triage',
            'status' => 'activo',
        ]);
        Shift::create([
            'user_id' => $this->nurse->id,
            'type' => 'rotativo',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->addHours(6),
            'status' => 'activo',
        ]);

        $this->admissions = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->admissions->assignRole('admisiones');

        $this->coordinator = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->coordinator->assignRole('coordinador');

        $this->auditor = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->auditor->assignRole('auditor');

        // Crear infraestructura física de prueba
        $site = Site::create(['code' => 'UBT-S1', 'name' => 'Sede Principal Ubaté']);
        $floor = Floor::create(['site_id' => $site->id, 'number' => 1, 'name' => 'Piso Urgencias']);
        $area = Area::create(['floor_id' => $floor->id, 'name' => 'Observación Adultos', 'type' => 'observacion']);
        $room = Room::create(['area_id' => $area->id, 'code' => 'H101', 'capacity' => 2]);
        $this->bed = Bed::create(['room_id' => $room->id, 'code' => 'P1-H101-A', 'status' => 'disponible']);

        // Crear paciente y episodio de prueba en estado 'preregistrado'
        $patient = Patient::create([
            'full_name' => 'Juan Carlos Restrepo',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1025777888',
            'document_index' => Patient::computeDocumentIndex('Cédula de ciudadanía', '1025777888'),
            'phone' => '3112223344',
        ]);

        $this->episode = EmergencyEpisode::create([
            'patient_id' => $patient->id,
            'public_code' => 'UBT9K8W2',
            'origin' => 'pre_registro',
            'status' => 'preregistrado',
            'reason' => 'Dolor abdominal agudo e hipotensión.',
            'pre_registration_expires_at' => now()->addHours(24),
        ]);
    }

    public function test_state_machine_full_transition_lifecycle(): void
    {
        $stateMachine = app(EpisodeStateMachine::class);
        $clinicalService = app(ClinicalService::class);

        // 1. Registrar llegada (preregistrado -> en_espera)
        $ep = $stateMachine->registerArrival($this->episode);
        $this->assertEquals('en_espera', $ep->status);
        $this->assertNotNull($ep->arrived_at);

        // 2. Asignar Triage (en_espera -> en_valoracion)
        $ep = $stateMachine->assignTriage($ep, 'II', $this->nurse);
        $this->assertEquals('en_valoracion', $ep->status);
        $this->assertEquals('II', $ep->triage_level);
        $this->assertEquals($this->nurse->id, $ep->triage_user_id);
        $this->assertNotNull($ep->triage_at);

        // 3. Confirmar Ingreso (en_valoracion -> ingresado)
        $ep = $stateMachine->confirmAdmission($ep);
        $this->assertEquals('ingresado', $ep->status);
        $this->assertNotNull($ep->admitted_at);
        $this->assertTrue($ep->admitted_at->gte($ep->arrived_at));

        // 4. Asignar camilla (solo permitido en estado 'ingresado')
        $bedAssignment = $clinicalService->assignBedToEpisode($ep, $this->bed);
        $this->assertNotNull($bedAssignment);
        $this->bed->refresh();
        $this->assertEquals('ocupada', $this->bed->status);

        // 5. Asignar médico responsable
        $clinicalService->assignStaff($ep, $this->doctor, 'responsable');
        $ep->refresh();
        $this->assertEquals($this->doctor->id, $ep->responsible_user_id);

        // 6. Registrar egreso (ingresado -> egresado)
        $ep = $stateMachine->discharge($ep, 'alta_medica');
        $this->assertEquals('egresado', $ep->status);
        $this->assertEquals('alta_medica', $ep->discharge_type);
        $this->assertNotNull($ep->discharged_at);
        $this->assertNotNull($ep->closed_at);

        // Al egresar, la camilla se libera y pasa a desinfección ('en_limpieza')
        $this->bed->refresh();
        $this->assertEquals('en_limpieza', $this->bed->status);

        // Las asignaciones de personal quedan finalizadas
        $this->assertDatabaseMissing('episode_assignments', [
            'episode_id' => $ep->id,
            'ended_at' => null,
        ]);
    }

    public function test_cannot_assign_bed_unless_episode_is_admitted(): void
    {
        $clinicalService = app(ClinicalService::class);

        // El episodio está en 'preregistrado'
        $this->expectException(ValidationException::class);
        $clinicalService->assignBedToEpisode($this->episode, $this->bed);
    }

    public function test_cannot_discharge_unless_episode_is_admitted(): void
    {
        $stateMachine = app(EpisodeStateMachine::class);

        // Intentar dar egreso desde 'preregistrado' o 'en_espera'
        $this->expectException(ValidationException::class);
        $stateMachine->discharge($this->episode, 'alta_medica');
    }

    public function test_cannot_confirm_admission_without_triage(): void
    {
        $stateMachine = app(EpisodeStateMachine::class);
        $ep = $stateMachine->registerArrival($this->episode);

        // En estado 'en_espera' sin haber pasado por triage
        $this->expectException(ValidationException::class);
        $stateMachine->confirmAdmission($ep);
    }

    public function test_closed_episode_rejects_ordinary_clinical_entries(): void
    {
        $stateMachine = app(EpisodeStateMachine::class);
        $clinicalService = app(ClinicalService::class);

        // Llevar el episodio a egresado
        $ep = $stateMachine->registerArrival($this->episode);
        $ep = $stateMachine->assignTriage($ep, 'III', $this->nurse);
        $ep = $stateMachine->confirmAdmission($ep);
        $ep = $stateMachine->discharge($ep, 'alta_medica');

        // Intentar agregar una evolución clínica ordinaria debe ser rechazado
        $this->expectException(ValidationException::class);
        $clinicalService->addClinicalEntry($ep, $this->doctor, 'nota', 'Evolución tardía no permitida');
    }

    public function test_closed_episode_allows_clarification_with_reason(): void
    {
        $stateMachine = app(EpisodeStateMachine::class);
        $clinicalService = app(ClinicalService::class);

        $ep = $stateMachine->registerArrival($this->episode);
        $ep = $stateMachine->assignTriage($ep, 'III', $this->nurse);
        $ep = $stateMachine->confirmAdmission($ep);

        // Entrada previa antes del cierre
        $originalEntry = $clinicalService->addClinicalEntry($ep, $this->doctor, 'diagnostico', 'Dolor abdominal');

        // Egresar
        $ep = $stateMachine->discharge($ep, 'alta_medica');

        // Agregar aclaración posterior con motivo justificado (excepción permitida por sección 7.3)
        $clarification = $clinicalService->addClinicalEntry(
            $ep,
            $this->doctor,
            'aclaracion',
            'Se precisa que el dolor cedió completamente tras analgesia.',
            $originalEntry->id,
            'Aclaración posterior solicitada por auditoría de caso'
        );

        $this->assertNotNull($clarification);
        $this->assertEquals('aclaracion', $clarification->type);
        $this->assertEquals($originalEntry->id, $clarification->references_entry_id);
    }

    public function test_clinical_entries_are_immutable(): void
    {
        $stateMachine = app(EpisodeStateMachine::class);
        $clinicalService = app(ClinicalService::class);

        $ep = $stateMachine->registerArrival($this->episode);
        $ep = $stateMachine->assignTriage($ep, 'II', $this->nurse);
        $ep = $stateMachine->confirmAdmission($ep);

        $entry = $clinicalService->addClinicalEntry($ep, $this->doctor, 'diagnostico', 'Apendicitis aguda sospechada');

        // Intento directo de update en modelo lanza RuntimeException
        $this->expectException(RuntimeException::class);
        $entry->update(['content' => 'Gastroenteritis']);
    }

    public function test_cannot_assign_staff_in_rest_or_inactive(): void
    {
        $stateMachine = app(EpisodeStateMachine::class);
        $clinicalService = app(ClinicalService::class);

        $ep = $stateMachine->registerArrival($this->episode);
        $ep = $stateMachine->assignTriage($ep, 'II', $this->nurse);
        $ep = $stateMachine->confirmAdmission($ep);

        // Crear médico en descanso
        $restingDoctor = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $restingDoctor->assignRole('medico');
        StaffProfile::create([
            'user_id' => $restingDoctor->id,
            'code' => 'MED-999999',
            'specialty' => 'Cirujano',
            'status' => 'en_descanso', // En descanso
        ]);

        $this->expectException(ValidationException::class);
        $clinicalService->assignStaff($ep, $restingDoctor, 'responsable');
    }

    public function test_calculated_active_patients_count(): void
    {
        $stateMachine = app(EpisodeStateMachine::class);
        $clinicalService = app(ClinicalService::class);

        $ep = $stateMachine->registerArrival($this->episode);
        $ep = $stateMachine->assignTriage($ep, 'II', $this->nurse);
        $ep = $stateMachine->confirmAdmission($ep);

        $this->assertEquals(0, $clinicalService->countActivePatients($this->doctor));

        $clinicalService->assignStaff($ep, $this->doctor, 'responsable');
        $this->assertEquals(1, $clinicalService->countActivePatients($this->doctor));

        // Al egresar el paciente, el contador activo decrementa a 0
        $stateMachine->discharge($ep, 'alta_medica');
        $this->assertEquals(0, $clinicalService->countActivePatients($this->doctor));
    }

    public function test_medical_record_read_audit(): void
    {
        // El médico asignado visualiza la historia clínica directamente
        $this->episode->update(['responsible_user_id' => $this->doctor->id]);

        $response = $this->actingAs($this->doctor)->get(route('episodes.show', $this->episode));
        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'historia.lectura',
            'user_id' => $this->doctor->id,
            'model_id' => $this->episode->id,
        ]);
    }

    public function test_exceptional_access_read_audit(): void
    {
        // Acceso excepcional con motivo (médico no asignado)
        $response = $this->actingAs($this->doctor)->get(route('episodes.show', [
            'episode' => $this->episode,
            'exceptional_reason' => 'Consulta urgente por relevo no programado',
        ]));
        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'historia.acceso_excepcional',
            'user_id' => $this->doctor->id,
            'model_id' => $this->episode->id,
        ]);
    }

    public function test_unassigned_doctor_without_reason_receives_break_glass_challenge(): void
    {
        // El médico NO está asignado y no envía motivo de justificación
        $response = $this->actingAs($this->doctor)->get(route('episodes.show', $this->episode));
        $response->assertStatus(200);

        // Debe renderizar la pantalla de control de acceso / Break-Glass
        $response->assertSee('Control de Custodia de Historia Clínica');
        $response->assertSee('Acceso Excepcional Requerido');
        $response->assertSee('ADVERTENCIA MÉDICO-LEGAL');
        $response->assertDontSee('Evolución y Entradas Clínicas (Inmutables)');
    }

    public function test_unassigned_doctor_cannot_add_clinical_entry_or_discharge(): void
    {
        // Episodio en estado ingresado asignado a la enfermera (el médico no está asignado)
        $this->episode->update([
            'status' => 'ingresado',
            'responsible_user_id' => $this->nurse->id,
        ]);

        // Intento de agregar evolución clínica por médico no asignado (403 Forbidden)
        $resEntry = $this->actingAs($this->doctor)->post(route('episodes.entries.store', $this->episode), [
            'type' => 'diagnostico',
            'content' => 'Intento de diagnóstico por médico no asignado',
        ]);
        $resEntry->assertStatus(403);

        // Intento de egreso por médico no asignado (403 Forbidden)
        $resDischarge = $this->actingAs($this->doctor)->post(route('episodes.discharge', $this->episode), [
            'discharge_type' => 'alta_medica',
        ]);
        $resDischarge->assertStatus(403);
    }

    public function test_role_authorization_for_clinical_actions(): void
    {
        // 1. Admisiones puede ver y registrar llegada
        $resArrival = $this->actingAs($this->admissions)->post(route('episodes.arrival', $this->episode));
        $resArrival->assertRedirect();
        $this->episode->refresh();
        $this->assertEquals('en_espera', $this->episode->status);

        // 2. Admisiones NO puede asignar triage (403 Forbidden)
        $resTriageAdm = $this->actingAs($this->admissions)->post(route('episodes.triage', $this->episode), [
            'triage_level' => 'II',
        ]);
        $resTriageAdm->assertStatus(403);

        // 3. Enfermera sí puede asignar triage
        $resTriageNurse = $this->actingAs($this->nurse)->post(route('episodes.triage', $this->episode), [
            'triage_level' => 'II',
        ]);
        $resTriageNurse->assertRedirect();
        $this->episode->refresh();
        $this->assertEquals('en_valoracion', $this->episode->status);

        // Asignar al médico como tratante responsable
        $this->episode->update(['responsible_user_id' => $this->doctor->id]);

        // 4. Médico confirma ingreso y agrega evolución
        $this->actingAs($this->doctor)->post(route('episodes.admission', $this->episode));
        $this->episode->refresh();
        $this->assertEquals('ingresado', $this->episode->status);

        $resEntry = $this->actingAs($this->doctor)->post(route('episodes.entries.store', $this->episode), [
            'type' => 'diagnostico',
            'content' => 'Sospecha de colecistitis litiásica.',
        ]);
        $resEntry->assertRedirect();

        // 5. Auditor NO puede egresar ni atender pacientes (403 Forbidden)
        $resAuditDischarge = $this->actingAs($this->auditor)->post(route('episodes.discharge', $this->episode), [
            'discharge_type' => 'alta_medica',
        ]);
        $resAuditDischarge->assertStatus(403);
    }

    public function test_cannot_assign_same_doctor_twice_as_responsible(): void
    {
        $clinicalService = app(ClinicalService::class);
        $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');

        $this->expectException(ValidationException::class);
        $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');
    }

    public function test_cannot_add_clinical_entry_to_preregistered_patient(): void
    {
        // El episodio está en preregistrado (el paciente aún no llega al hospital)
        $this->assertSame('preregistrado', $this->episode->status);

        $resEntry = $this->actingAs($this->doctor)->post(route('episodes.entries.store', $this->episode), [
            'type' => 'diagnostico',
            'content' => 'Intento de diagnóstico antes de llegada física',
        ]);

        $resEntry->assertStatus(403);
    }

    public function test_cannot_suspend_doctor_with_active_assigned_patients(): void
    {
        $clinicalService = app(ClinicalService::class);
        $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('administrador');

        $this->expectException(ValidationException::class);
        app(UserService::class)->suspend($this->doctor, 'Sanción disciplinaria', $admin);
    }

    public function test_cannot_assign_doctor_as_support_if_already_responsible(): void
    {
        $clinicalService = app(ClinicalService::class);
        $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');

        $this->expectException(ValidationException::class);
        $clinicalService->assignStaff($this->episode, $this->doctor, 'apoyo');
    }

    public function test_assigning_support_as_responsible_ends_prior_support_assignment(): void
    {
        $clinicalService = app(ClinicalService::class);
        $assignmentSupport = $clinicalService->assignStaff($this->episode, $this->doctor, 'apoyo');
        $this->assertNull($assignmentSupport->fresh()->ended_at);

        // Promover al médico a responsable en el mismo episodio
        $assignmentResp = $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');

        // La asignación previa como apoyo debe haberse cerrado
        $this->assertNotNull($assignmentSupport->fresh()->ended_at);
        $this->assertNull($assignmentResp->fresh()->ended_at);
        $this->assertSame(1, $clinicalService->countActivePatients($this->doctor));
    }

    public function test_medical_dashboard_renders_with_assigned_patients_and_metrics(): void
    {
        $clinicalService = app(ClinicalService::class);
        $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');

        // Transicionar a valoración para que sea activo
        $this->episode->update([
            'status' => 'en_valoracion',
            'triage_level' => 'II',
        ]);

        $response = $this->actingAs($this->doctor)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Mis Pacientes');
        $response->assertSee('Médico Responsable');
        $response->assertSee('Nivel II');
        $response->assertSee($this->episode->public_code);
        $response->assertDontSee('Noticias');
        $response->assertDontSee('Imágenes');
    }

    public function test_episodes_mis_pacientes_tab_filters_only_assigned_patients(): void
    {
        $clinicalService = app(ClinicalService::class);
        $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');
        $this->episode->update(['status' => 'en_valoracion']);

        // Crear un segundo episodio asignado a la enfermera
        $secondPatient = Patient::create([
            'full_name' => 'María Camila Gómez',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1025999888',
            'document_index' => Patient::computeDocumentIndex('Cédula de ciudadanía', '1025999888'),
            'phone' => '3123334455',
        ]);
        $secondEpisode = EmergencyEpisode::create([
            'patient_id' => $secondPatient->id,
            'public_code' => 'UBT22222',
            'status' => 'en_valoracion',
            'origin' => 'espontaneo',
            'responsible_user_id' => $this->nurse->id,
            'reason' => 'Dolor abdominal agudo',
        ]);

        // Consulta en pestaña mis_pacientes para el médico
        $response = $this->actingAs($this->doctor)->get(route('episodes.index', ['tab' => 'mis_pacientes']));
        $response->assertStatus(200);
        $response->assertSee($this->episode->public_code);
        $response->assertDontSee('UBT22222');

        // Consulta en pestaña todos los activos muestra ambos
        $responseActivos = $this->actingAs($this->doctor)->get(route('episodes.index', ['tab' => 'activos']));
        $responseActivos->assertStatus(200);
        $responseActivos->assertSee($this->episode->public_code);
        $responseActivos->assertSee('UBT22222');
    }

    public function test_support_staff_can_deactivate_themselves_from_episode(): void
    {
        $clinicalService = app(ClinicalService::class);
        $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');
        $assignmentSupport = $clinicalService->assignStaff($this->episode, $this->nurse, 'apoyo');

        $this->assertSame(1, $clinicalService->countActivePatients($this->nurse));
        $this->assertNull($assignmentSupport->fresh()->ended_at);

        $res = $this->actingAs($this->nurse)->post(route('episodes.staff.end', [$this->episode, $assignmentSupport]), [
            'reason' => 'Toma de signos y vía periférica completada',
        ]);

        $res->assertRedirect(route('episodes.show', $this->episode));
        $res->assertSessionHas('success');

        $this->assertNotNull($assignmentSupport->fresh()->ended_at);
        $this->assertSame(0, $clinicalService->countActivePatients($this->nurse));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'episodio.apoyo_finalizado',
            'model_type' => 'EmergencyEpisode',
            'model_id' => $this->episode->id,
        ]);
    }

    public function test_responsible_doctor_can_deactivate_support_staff(): void
    {
        $clinicalService = app(ClinicalService::class);
        $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');
        $assignmentSupport = $clinicalService->assignStaff($this->episode, $this->nurse, 'apoyo');

        $res = $this->actingAs($this->doctor)->post(route('episodes.staff.end', [$this->episode, $assignmentSupport]));

        $res->assertRedirect(route('episodes.show', $this->episode));
        $res->assertSessionHas('success');
        $this->assertNotNull($assignmentSupport->fresh()->ended_at);
    }

    public function test_cannot_deactivate_responsible_doctor_via_end_support_endpoint(): void
    {
        $clinicalService = app(ClinicalService::class);
        $assignmentResp = $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');

        // La policy rechaza si el rol no es 'apoyo'
        $res = $this->actingAs($this->doctor)->post(route('episodes.staff.end', [$this->episode, $assignmentResp]));

        $res->assertStatus(403);
        $this->assertNull($assignmentResp->fresh()->ended_at);
    }

    public function test_unauthorized_user_cannot_deactivate_support_staff(): void
    {
        $clinicalService = app(ClinicalService::class);
        $clinicalService->assignStaff($this->episode, $this->doctor, 'responsable');
        $assignmentSupport = $clinicalService->assignStaff($this->episode, $this->nurse, 'apoyo');

        $res = $this->actingAs($this->auditor)->post(route('episodes.staff.end', [$this->episode, $assignmentSupport]));

        $res->assertStatus(403);
        $this->assertNull($assignmentSupport->fresh()->ended_at);
    }
}
