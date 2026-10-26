<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\EmergencyEpisode;
use App\Models\Patient;
use App\Models\User;
use App\Services\IdentityReconciliationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IdentityReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $doctor;

    protected User $nurse;

    protected User $admissions;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->admin->assignRole('administrador');

        $this->doctor = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->doctor->assignRole('medico');

        $this->nurse = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->nurse->assignRole('enfermero');

        $this->admissions = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->admissions->assignRole('admisiones');

        $this->author = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->author->assignRole('autor');
    }

    public function test_authorized_staff_can_view_emergency_nn_registration_form(): void
    {
        // Doctor, admisiones, enfermero y administrador pueden acceder al registro NN
        $this->actingAs($this->doctor)->get('/episodios/registro-nn')->assertOk();
        $this->actingAs($this->admissions)->get('/episodios/registro-nn')->assertOk();
        $this->actingAs($this->nurse)->get('/episodios/registro-nn')->assertOk();
        $this->actingAs($this->admin)->get('/episodios/registro-nn')->assertOk();
    }

    public function test_unauthorized_user_cannot_access_emergency_nn_registration(): void
    {
        // Rol no autorizado (autor) recibe 403
        $this->actingAs($this->author)->get('/episodios/registro-nn')->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        // No autenticado redirige a login
        $this->get('/episodios/registro-nn')->assertRedirect('/login');
    }

    public function test_validation_requires_mandatory_nn_fields(): void
    {
        $response = $this->actingAs($this->admissions)->post('/episodios/registro-nn', []);

        $response->assertSessionHasErrors([
            'apparent_sex',
            'estimated_age',
            'distinguishing_marks',
            'arrival_mode',
            'brought_by_name',
            'reason',
        ]);
    }

    public function test_successful_emergency_nn_registration(): void
    {
        $payload = [
            'apparent_sex' => 'masculino',
            'estimated_age' => 'Aproximadamente 40 años',
            'distinguishing_marks' => 'Tatuaje de dragón en brazo derecho, cicatriz frontal, viste chaqueta negra y jeans',
            'arrival_mode' => 'ambulancia',
            'brought_by_name' => 'SAMU Móvil 04 - Paramédico Carlos Ruiz',
            'brought_by_contact' => 'Placa OBF-789 / Móvil 3109876543',
            'reason' => 'Paciente inconsciente hallado en vía pública tras colisión vehicular',
            'initial_notes' => 'Glasgow 8/15, pupilas reactivas, sin documentos en pertenencias',
        ];

        $response = $this->actingAs($this->admissions)->post('/episodios/registro-nn', $payload);

        $response->assertRedirect();

        // Verificar paciente creado con estado no_identificado
        $patient = Patient::where('identity_status', 'no_identificado')->first();
        $this->assertNotNull($patient);
        $this->assertNull($patient->document_number);
        $this->assertNull($patient->document_index);
        $this->assertSame('masculino', $patient->gender);

        // Verificar episodio NN creado
        $episode = EmergencyEpisode::where('is_nn', true)->first();
        $this->assertNotNull($episode);
        $this->assertStringStartsWith('NN-', $episode->nn_code);
        $this->assertSame($patient->id, $episode->patient_id);
        $this->assertSame($patient->id, $episode->original_nn_patient_id);
        $this->assertSame('en_espera', $episode->status);
        $this->assertNotNull($episode->arrived_at);
        $this->assertSame('interés vital, paciente sin capacidad', $episode->no_consent_reason);

        // Verificar excepción de consentimiento creada
        $consent = Consent::where('episode_id', $episode->id)->first();
        $this->assertNotNull($consent);
        $this->assertSame('excepcion_legal_interes_vital', $consent->granted_by);

        // Verificar registro de auditoría
        $this->assertTrue(AuditLog::where('event', 'episodio.ingreso_nn_creado')->exists());
    }

    public function test_sequential_nn_code_generation(): void
    {
        $service = app(IdentityReconciliationService::class);

        $payload = [
            'apparent_sex' => 'femenino',
            'estimated_age' => '30 años',
            'distinguishing_marks' => 'Sin señas visibles',
            'arrival_mode' => 'policia',
            'brought_by_name' => 'Patrulla 12',
            'reason' => 'Paciente desorientada en terminal',
        ];

        $ep1 = $service->registerEmergencyNn($payload, $this->admissions);
        $ep2 = $service->registerEmergencyNn($payload, $this->admissions);
        $ep3 = $service->registerEmergencyNn($payload, $this->admissions);

        $date = now()->format('Ymd');
        $this->assertSame("NN-{$date}-0001", $ep1->nn_code);
        $this->assertSame("NN-{$date}-0002", $ep2->nn_code);
        $this->assertSame("NN-{$date}-0003", $ep3->nn_code);
    }

    public function test_authorized_roles_can_view_reconciliation_form(): void
    {
        $service = app(IdentityReconciliationService::class);
        $episode = $service->registerEmergencyNn([
            'apparent_sex' => 'masculino',
            'estimated_age' => '50 años',
            'distinguishing_marks' => 'Cicatriz en rodilla izquierda',
            'arrival_mode' => 'ambulancia',
            'brought_by_name' => 'Bomberos',
            'reason' => 'Caída de altura',
        ], $this->admissions);

        // Doctor, admisiones y admin pueden acceder a conciliar
        $this->actingAs($this->doctor)->get("/episodios/{$episode->id}/conciliar")->assertOk();
        $this->actingAs($this->admissions)->get("/episodios/{$episode->id}/conciliar")->assertOk();
        $this->actingAs($this->admin)->get("/episodios/{$episode->id}/conciliar")->assertOk();

        // Enfermero no tiene permiso de conciliación de identidad
        $this->actingAs($this->nurse)->get("/episodios/{$episode->id}/conciliar")->assertForbidden();
    }

    public function test_cannot_access_reconciliation_for_non_nn_episode(): void
    {
        $patient = Patient::create([
            'full_name' => 'Paciente Ordinario',
            'identity_status' => 'identificado',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1020304050',
            'document_index' => Patient::computeDocumentIndex('Cédula de ciudadanía', '1020304050'),
        ]);

        $episode = EmergencyEpisode::create([
            'patient_id' => $patient->id,
            'public_code' => EmergencyEpisode::generatePublicCode(),
            'is_nn' => false,
            'origin' => 'pre_registro',
            'status' => 'en_espera',
            'reason' => 'Fiebre persistente',
        ]);

        $this->actingAs($this->doctor)->get("/episodios/{$episode->id}/conciliar")->assertForbidden();
    }

    public function test_reconciliation_requires_evidence_of_at_least_10_chars(): void
    {
        $service = app(IdentityReconciliationService::class);
        $episode = $service->registerEmergencyNn([
            'apparent_sex' => 'masculino',
            'estimated_age' => '25 años',
            'distinguishing_marks' => 'Tatuaje en antebrazo',
            'arrival_mode' => 'transeunte',
            'brought_by_name' => 'Vecino',
            'reason' => 'Desmayo',
        ], $this->admissions);

        $response = $this->actingAs($this->admissions)->post("/episodios/{$episode->id}/conciliar", [
            'reconciliation_mode' => 'new',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1012345678',
            'full_name' => 'Juan Pérez Gómez',
            'reconciliation_evidence' => 'Cédula', // Demasiado corto (< 10 caracteres)
        ]);

        $response->assertSessionHasErrors('reconciliation_evidence');
    }

    public function test_reconcile_with_new_identified_patient_preserves_nn_history(): void
    {
        $service = app(IdentityReconciliationService::class);
        $episode = $service->registerEmergencyNn([
            'apparent_sex' => 'masculino',
            'estimated_age' => '38 años',
            'distinguishing_marks' => 'Tatuaje de ancla en hombro izquierdo',
            'arrival_mode' => 'ambulancia',
            'brought_by_name' => 'Cruz Roja',
            'reason' => 'Trauma craneal leve',
        ], $this->admissions);

        $initialPatientId = $episode->patient_id;
        $nnCode = $episode->nn_code;

        $response = $this->actingAs($this->admissions)->post("/episodios/{$episode->id}/conciliar", [
            'reconciliation_mode' => 'new',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1025896321',
            'full_name' => 'Mateo Beltrán Ospina',
            'birth_date' => '1988-05-14',
            'gender' => 'masculino',
            'phone' => '3114567890',
            'reconciliation_evidence' => 'Cédula de ciudadanía física original aportada por la hermana María Beltrán, cotejo fotográfico coincidente.',
        ]);

        $response->assertRedirect();

        $freshEpisode = $episode->fresh();

        // 1. El episodio ahora apunta al nuevo paciente identificado
        $this->assertNotSame($initialPatientId, $freshEpisode->patient_id);
        $this->assertSame('Mateo Beltrán Ospina', $freshEpisode->patient->full_name);
        $this->assertSame('1025896321', $freshEpisode->patient->document_number);
        $this->assertSame('identificado', $freshEpisode->patient->identity_status);

        // 2. El registro original de paciente NN no se borró
        $originalPatient = Patient::find($initialPatientId);
        $this->assertNotNull($originalPatient);
        $this->assertSame('no_identificado', $originalPatient->identity_status);
        $this->assertFalse($originalPatient->trashed());

        // 3. El episodio conserva el identificador NN original y el puntero al paciente NN
        $this->assertSame($initialPatientId, $freshEpisode->original_nn_patient_id);
        $this->assertSame($nnCode, $freshEpisode->nn_code);
        $this->assertTrue($freshEpisode->isNn());
        $this->assertTrue($freshEpisode->isReconciled());
        $this->assertNotNull($freshEpisode->reconciled_at);
        $this->assertSame($this->admissions->id, $freshEpisode->reconciled_by_user_id);

        // 4. Verificación de auditoría
        $this->assertTrue(AuditLog::where('event', 'episodio.identidad_conciliada')->exists());
    }

    public function test_reconcile_with_existing_patient_in_database(): void
    {
        // Paciente preexistente en la base de datos
        $existingPatient = Patient::create([
            'full_name' => 'Laura Sofía Castro',
            'identity_status' => 'identificado',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '52147852',
            'document_index' => Patient::computeDocumentIndex('Cédula de ciudadanía', '52147852'),
            'gender' => 'femenino',
            'phone' => '3201234567',
        ]);

        $service = app(IdentityReconciliationService::class);
        $episode = $service->registerEmergencyNn([
            'apparent_sex' => 'femenino',
            'estimated_age' => '42 años',
            'distinguishing_marks' => 'Sin señas articulares',
            'arrival_mode' => 'bomberos',
            'brought_by_name' => 'Móvil Bomberos 3',
            'reason' => 'Inhalación de humo',
        ], $this->admissions);

        $initialPatientId = $episode->patient_id;

        $response = $this->actingAs($this->admissions)->post("/episodios/{$episode->id}/conciliar", [
            'reconciliation_mode' => 'existing',
            'existing_patient_id' => $existingPatient->id,
            'reconciliation_evidence' => 'Verificación con base de datos del hospital por huella y confirmación por esposo.',
        ]);

        $response->assertRedirect();

        $fresh = $episode->fresh();
        $this->assertSame($existingPatient->id, $fresh->patient_id);
        $this->assertSame($initialPatientId, $fresh->original_nn_patient_id);
        $this->assertTrue($fresh->isReconciled());

        // El registro inicial NN sigue en la base de datos intacto
        $this->assertDatabaseHas('patients', ['id' => $initialPatientId]);
    }

    public function test_cannot_reconcile_already_reconciled_episode(): void
    {
        $service = app(IdentityReconciliationService::class);
        $episode = $service->registerEmergencyNn([
            'apparent_sex' => 'masculino',
            'estimated_age' => '30 años',
            'distinguishing_marks' => 'Ninguna',
            'arrival_mode' => 'propio_pie',
            'brought_by_name' => 'Él mismo',
            'reason' => 'Desorientación',
        ], $this->admissions);

        $service->reconcileIdentity($episode, [
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '79456123',
            'full_name' => 'Andrés Mendoza',
            'reconciliation_evidence' => 'Cédula presentada posteriormente por el paciente al recuperar orientación.',
        ], $this->admissions);

        // Segundo intento debe lanzar excepción
        $this->expectException(ValidationException::class);
        $service->reconcileIdentity($episode->fresh(), [
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '79456123',
            'full_name' => 'Andrés Mendoza',
            'reconciliation_evidence' => 'Segundo intento redundante.',
        ], $this->admissions);
    }

    public function test_search_patient_endpoint_returns_json(): void
    {
        $patient = Patient::create([
            'full_name' => 'Diego Fernando Morales',
            'identity_status' => 'identificado',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '80123987',
            'document_index' => Patient::computeDocumentIndex('Cédula de ciudadanía', '80123987'),
            'gender' => 'masculino',
            'phone' => '3128901234',
        ]);

        $response = $this->actingAs($this->admissions)->getJson('/episodios/pacientes/buscar?'.http_build_query([
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '80123987',
        ]));

        $response->assertOk()
            ->assertJson([
                'found' => true,
                'patient' => [
                    'id' => $patient->id,
                    'full_name' => 'Diego Fernando Morales',
                    'document_number' => '80123987',
                ],
            ]);
    }
}
