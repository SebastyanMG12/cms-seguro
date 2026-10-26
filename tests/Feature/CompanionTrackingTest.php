<?php

namespace Tests\Feature;

use App\Models\Companion;
use App\Models\EmergencyEpisode;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\ClinicalService;
use App\Services\CompanionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanionTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;

    private Patient $patient;

    private EmergencyEpisode $episode;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('companion_login:127.0.0.1:UBTTEST1');
        RateLimiter::clear('companion_login:127.0.0.1:UBTNN111');

        Role::firstOrCreate(['name' => 'medico']);
        Permission::firstOrCreate(['name' => 'episodios.ver']);
        Permission::firstOrCreate(['name' => 'episodios.atender']);

        $role = Role::findByName('medico');
        $role->givePermissionTo(['episodios.ver', 'episodios.atender']);

        $this->doctor = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->doctor->assignRole('medico');
        StaffProfile::create([
            'user_id' => $this->doctor->id,
            'code' => 'MED-TEST01',
            'specialty' => 'Medicina de Urgencias',
            'status' => 'activo',
        ]);

        $this->patient = Patient::create([
            'full_name' => 'Carlos Andrés Gómez Pérez',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1025888999',
            'document_index' => Patient::computeDocumentIndex('Cédula de ciudadanía', '1025888999'),
            'phone' => '3128889999',
        ]);

        $this->episode = EmergencyEpisode::create([
            'patient_id' => $this->patient->id,
            'public_code' => 'UBTTEST1',
            'status' => 'ingresado',
            'origin' => 'espontaneo',
            'arrived_at' => now()->subHours(2),
            'triage_level' => 'III',
            'triage_at' => now()->subMinutes(100),
            'admitted_at' => now()->subMinutes(60),
            'reason' => 'Dolor abdominal cólico',
            'responsible_user_id' => $this->doctor->id,
        ]);
    }

    public function test_companion_login_page_renders_successfully(): void
    {
        $response = $this->get(route('companion.login'));

        $response->assertStatus(200);
        $response->assertSee('Seguimiento de Urgencias para Acompañantes');
        $response->assertSee('Código del Paciente');
        $response->assertSee('PIN de Acceso');
        $response->assertSee('Ley 1581 de 2012');
    }

    public function test_staff_can_generate_plain_pin_and_it_is_hashed_in_database(): void
    {
        $response = $this->actingAs($this->doctor)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('episodes.companion.pin', $this->episode));

        $response->assertRedirect(route('episodes.show', $this->episode));
        $response->assertSessionHas('generated_pin');

        $plainPin = session('generated_pin');
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $plainPin);

        $companion = Companion::where('episode_id', $this->episode->id)->first();
        $this->assertNotNull($companion);
        $this->assertTrue(Hash::check($plainPin, $companion->pin_hash));
        $this->assertEquals($this->doctor->id, $companion->verified_by_user_id);
        $this->assertNotNull($companion->verified_at);
        $this->assertNull($companion->revoked_at);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'acompanante.pin_generado',
            'model_type' => 'EmergencyEpisode',
            'model_id' => $this->episode->id,
        ]);
    }

    public function test_companion_can_authenticate_with_code_and_pin_and_view_status(): void
    {
        $companionService = app(CompanionService::class);
        $pin = $companionService->generatePin($this->episode, $this->doctor);

        $response = $this->post(route('companion.auth'), [
            'public_code' => 'UBTTEST1',
            'pin' => $pin,
        ]);

        $response->assertRedirect(route('companion.status'));
        $this->assertEquals($this->episode->id, session('companion_episode_id'));

        $statusResponse = $this->get(route('companion.status'));
        $statusResponse->assertStatus(200);
        $statusResponse->assertSee('Carlos A.');
        $statusResponse->assertSee('Ingresado en el Servicio de Urgencias');
        $statusResponse->assertSee('UBTTEST1');
        $statusResponse->assertSee('Línea de Tiempo del Paciente');
    }

    public function test_invalid_credentials_returns_generic_error_and_does_not_reveal_detail(): void
    {
        $companionService = app(CompanionService::class);
        $pin = $companionService->generatePin($this->episode, $this->doctor);

        // 1. Código inexistente
        $resWrongCode = $this->post(route('companion.auth'), [
            'public_code' => 'UBTWRONG',
            'pin' => $pin,
        ]);
        $resWrongCode->assertSessionHasErrors(['auth']);
        $this->assertEquals(
            'Los datos no coinciden. Verifique el código del paciente y el PIN entregado por el personal asistencial.',
            session('errors')->first('auth')
        );

        // 2. PIN incorrecto
        $resWrongPin = $this->post(route('companion.auth'), [
            'public_code' => 'UBTTEST1',
            'pin' => '000000',
        ]);
        $resWrongPin->assertSessionHasErrors(['auth']);
        $this->assertEquals(
            'Los datos no coinciden. Verifique el código del paciente y el PIN entregado por el personal asistencial.',
            session('errors')->first('auth')
        );
    }

    public function test_rate_limiting_locks_out_after_consecutive_failed_attempts(): void
    {
        $companionService = app(CompanionService::class);
        $companionService->generatePin($this->episode, $this->doctor);

        // Enviar 5 intentos fallidos
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('companion.auth'), [
                'public_code' => 'UBTTEST1',
                'pin' => '11111'.$i,
            ]);
        }

        // El 6to intento debe recibir error de bloqueo temporal
        $resBlocked = $this->post(route('companion.auth'), [
            'public_code' => 'UBTTEST1',
            'pin' => '999999',
        ]);

        $resBlocked->assertSessionHasErrors(['auth']);
        $this->assertStringContainsString('Demasiados intentos fallidos', session('errors')->first('auth'));
    }

    public function test_sensitive_patient_data_is_never_leaked_in_companion_portal(): void
    {
        $companionService = app(CompanionService::class);
        $pin = $companionService->generatePin($this->episode, $this->doctor);

        $this->post(route('companion.auth'), [
            'public_code' => 'UBTTEST1',
            'pin' => $pin,
        ]);

        $statusResponse = $this->get(route('companion.status'));
        $statusResponse->assertStatus(200);

        // Datos sensibles NO deben aparecer en la respuesta HTML
        $statusResponse->assertDontSee('1025888999'); // Documento de identidad
        $statusResponse->assertDontSee('3128889999'); // Teléfono del paciente
        $statusResponse->assertDontSee('Gómez Pérez'); // Apellidos completos sin enmascarar
        $statusResponse->assertDontSee('Carlos Andrés Gómez Pérez');
    }

    public function test_unidentified_nn_patient_shows_nn_code_in_companion_portal(): void
    {
        $nnEpisode = EmergencyEpisode::create([
            'patient_id' => null,
            'public_code' => 'UBTNN111',
            'is_nn' => true,
            'nn_code' => 'NN-20261025-0042',
            'apparent_sex' => 'masculino',
            'estimated_age' => 35,
            'status' => 'ingresado',
            'origin' => 'trauma_ambulancia',
            'arrived_at' => now()->subHours(1),
            'reason' => 'Politraumatismo en vía pública',
        ]);

        $companionService = app(CompanionService::class);
        $pin = $companionService->generatePin($nnEpisode, $this->doctor);

        $this->post(route('companion.auth'), [
            'public_code' => 'UBTNN111',
            'pin' => $pin,
        ]);

        $statusResponse = $this->get(route('companion.status'));
        $statusResponse->assertStatus(200);
        $statusResponse->assertSee('NN-20261025-0042');
    }

    public function test_clinical_information_shown_only_when_consent_is_granted(): void
    {
        $companionService = app(CompanionService::class);
        $pin = $companionService->generatePin($this->episode, $this->doctor);

        // Agregar una entrada clínica al episodio
        app(ClinicalService::class)->addClinicalEntry(
            $this->episode,
            $this->doctor,
            'diagnostico',
            'Apendicitis aguda en estudio'
        );

        $this->post(route('companion.auth'), [
            'public_code' => 'UBTTEST1',
            'pin' => $pin,
        ]);

        // CASO A: Sin consentimiento
        $resNoConsent = $this->get(route('companion.status'));
        $resNoConsent->assertStatus(200);
        $resNoConsent->assertDontSee('Apendicitis aguda en estudio');
        $resNoConsent->assertSee('Protección de Datos Médicos (Ley 1581 de 2012)');

        // CASO B: Personal asistencial activa consentimiento
        $this->actingAs($this->doctor)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('episodes.companion.consent', $this->episode), [
                'enable' => '1',
                'granted_by' => 'paciente',
            ]);

        $resWithConsent = $this->withSession(['companion_episode_id' => $this->episode->id])->get(route('companion.status'));
        $resWithConsent->assertStatus(200);
        $resWithConsent->assertSee('Información Clínica Autorizada');
        $resWithConsent->assertSee('Apendicitis aguda en estudio');
        $resWithConsent->assertSee('Autorizado por el paciente');

        // CASO C: Paciente revoca consentimiento
        $this->actingAs($this->doctor)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('episodes.companion.consent', $this->episode), [
                'enable' => '0',
            ]);

        $resRevokedConsent = $this->withSession(['companion_episode_id' => $this->episode->id])->get(route('companion.status'));
        $resRevokedConsent->assertStatus(200);
        $resRevokedConsent->assertDontSee('Apendicitis aguda en estudio');
        $resRevokedConsent->assertSee('Protección de Datos Médicos (Ley 1581 de 2012)');
    }

    public function test_staff_can_revoke_companion_access_and_subsequent_attempts_are_blocked(): void
    {
        $companionService = app(CompanionService::class);
        $pin = $companionService->generatePin($this->episode, $this->doctor);

        // Acompañante inicia sesión
        $this->post(route('companion.auth'), [
            'public_code' => 'UBTTEST1',
            'pin' => $pin,
        ]);
        $this->get(route('companion.status'))->assertStatus(200);

        // Personal revoca acceso
        $resRevoke = $this->actingAs($this->doctor)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('episodes.companion.revoke', $this->episode), [
                'reason' => 'Custodia legal disputada por familiares',
            ]);
        $resRevoke->assertRedirect(route('episodes.show', $this->episode));

        // Sesión activa queda bloqueada y es expulsada a login
        $resActiveSession = $this->withSession(['companion_episode_id' => $this->episode->id])->get(route('companion.status'));
        $resActiveSession->assertRedirect(route('companion.login'));
        $resActiveSession->assertSessionHasErrors(['auth']);

        // Nuevo intento de login con ese código recibe rechazo de revocación
        $resNewLogin = $this->post(route('companion.auth'), [
            'public_code' => 'UBTTEST1',
            'pin' => $pin,
        ]);
        $resNewLogin->assertSessionHasErrors(['auth']);
        $this->assertStringContainsString('revocado', session('errors')->first('auth'));
    }

    public function test_companion_access_expires_24_hours_after_patient_discharge(): void
    {
        $companionService = app(CompanionService::class);
        $pin = $companionService->generatePin($this->episode, $this->doctor);

        // Marcar episodio como egresado hace 25 horas
        $this->episode->update([
            'status' => 'egresado',
            'discharge_type' => 'alta_medica',
            'discharged_at' => now()->subHours(25),
            'closed_at' => now()->subHours(25),
        ]);

        $response = $this->post(route('companion.auth'), [
            'public_code' => 'UBTTEST1',
            'pin' => $pin,
        ]);

        $response->assertSessionHasErrors(['auth']);
        $this->assertStringContainsString('ha expirado tras el egreso', session('errors')->first('auth'));
    }

    public function test_companion_logout_clears_session(): void
    {
        $companionService = app(CompanionService::class);
        $pin = $companionService->generatePin($this->episode, $this->doctor);

        $this->post(route('companion.auth'), [
            'public_code' => 'UBTTEST1',
            'pin' => $pin,
        ]);
        $this->assertTrue(session()->has('companion_episode_id'));

        $resLogout = $this->post(route('companion.logout'));
        $resLogout->assertRedirect(route('companion.login'));
        $this->assertFalse(session()->has('companion_episode_id'));
    }

    public function test_companion_can_authenticate_using_patient_last_4_document_digits_by_default(): void
    {
        // Patient document is 1025888999 -> Default PIN is '8999'
        $this->assertEquals('8999', $this->episode->getDefaultCompanionPin());
        $this->assertEquals('8999', $this->episode->getCompanionPin());
        $this->assertStringContainsString('1025888999', $this->episode->getCompanionPinSourceDescription());

        // Companion enters public_code and last 4 digits of document
        $response = $this->post(route('companion.auth'), [
            'public_code' => 'UBTTEST1',
            'pin' => '8999',
        ]);

        $response->assertRedirect(route('companion.status'));
        $this->assertEquals($this->episode->id, session('companion_episode_id'));

        $statusResponse = $this->get(route('companion.status'));
        $statusResponse->assertStatus(200);
        $statusResponse->assertSee('Carlos A.');
    }

    public function test_doctor_and_admin_see_companion_credentials_in_show_view(): void
    {
        // Doctor views episode
        $resDoctor = $this->actingAs($this->doctor)
            ->withSession(['two_factor_authenticated' => true])
            ->get(route('episodes.show', $this->episode));

        $resDoctor->assertStatus(200);
        $resDoctor->assertSee('Credenciales de Consulta Web');
        $resDoctor->assertSee('UBTTEST1');
        $resDoctor->assertSee('8999');
        $resDoctor->assertSee('Últimos 4 dígitos del documento');

        // Admin views episode
        $adminRole = Role::firstOrCreate(['name' => 'administrador']);
        $adminRole->givePermissionTo('episodios.ver');
        $admin = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $admin->assignRole('administrador');

        $resAdmin = $this->actingAs($admin)
            ->withSession(['two_factor_authenticated' => true])
            ->get(route('episodes.show', $this->episode));

        $resAdmin->assertStatus(200);
        $resAdmin->assertSee('Credenciales de Consulta Web');
        $resAdmin->assertSee('8999');
    }

    public function test_doctor_can_reset_pin_to_document_digits_or_generate_random_6_digit_pin(): void
    {
        // 1. Generate 6-digit random PIN
        $resRandom = $this->actingAs($this->doctor)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('episodes.companion.pin', $this->episode), [
                'mode' => 'random',
            ]);

        $resRandom->assertRedirect(route('episodes.show', $this->episode));
        $generatedPin = session('generated_pin');
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $generatedPin);

        $this->episode->refresh();
        $this->assertEquals($generatedPin, $this->episode->getCompanionPin());
        $this->assertEquals('PIN asignado en hospital', $this->episode->getCompanionPinSourceDescription());

        // 2. Reset PIN to document digits
        $resReset = $this->actingAs($this->doctor)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('episodes.companion.pin', $this->episode), [
                'mode' => 'default',
            ]);

        $resReset->assertRedirect(route('episodes.show', $this->episode));
        $this->episode->refresh();
        $this->assertEquals('8999', $this->episode->getCompanionPin());
        $this->assertStringContainsString('1025888999', $this->episode->getCompanionPinSourceDescription());
    }

    public function test_unassigned_doctor_cannot_manage_companion_pin_or_consent(): void
    {
        $otherDoctor = User::factory()->create(['status' => 'active', 'two_factor_confirmed_at' => now()]);
        $otherDoctor->assignRole('medico');
        StaffProfile::create([
            'user_id' => $otherDoctor->id,
            'code' => 'MED-OTHER01',
            'specialty' => 'Medicina Interna',
            'status' => 'activo',
        ]);

        // Attempting to generate PIN without assignment fails with 403
        $resPin = $this->actingAs($otherDoctor)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('episodes.companion.pin', $this->episode), [
                'mode' => 'random',
            ]);
        $resPin->assertStatus(403);

        // Attempting to toggle clinical consent without assignment fails with 403
        $resConsent = $this->actingAs($otherDoctor)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('episodes.companion.consent', $this->episode), [
                'enable' => '1',
            ]);
        $resConsent->assertStatus(403);

        // Assigned doctor CAN toggle clinical consent
        $resAssignedConsent = $this->actingAs($this->doctor)
            ->withSession(['two_factor_authenticated' => true])
            ->post(route('episodes.companion.consent', $this->episode), [
                'enable' => '1',
            ]);
        $resAssignedConsent->assertRedirect(route('episodes.show', $this->episode));
        $this->assertTrue($this->episode->fresh()->hasActiveCompanionConsent());
    }
}
