<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\ClinicalEntry;
use App\Models\Companion;
use App\Models\EmergencyEpisode;
use App\Models\EpisodeClinicalData;
use App\Models\Floor;
use App\Models\Patient;
use App\Models\Room;
use App\Models\Site;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected User $otherDoctor;

    protected User $nurse;

    protected User $auditor;

    protected User $admin;

    protected EmergencyEpisode $episode;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->doctor = User::factory()->create(['name' => 'Dr. Martín Valenzuela', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->doctor->assignRole('medico');
        StaffProfile::create(['user_id' => $this->doctor->id, 'code' => 'MED-701', 'specialty' => 'Urgenciología', 'status' => 'activo']);

        $this->otherDoctor = User::factory()->create(['name' => 'Dra. Sandra Ortiz', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->otherDoctor->assignRole('medico');
        StaffProfile::create(['user_id' => $this->otherDoctor->id, 'code' => 'MED-702', 'specialty' => 'Cirugía', 'status' => 'activo']);

        $this->nurse = User::factory()->create(['name' => 'Enf. Patricia Gómez', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->nurse->assignRole('enfermero');
        StaffProfile::create(['user_id' => $this->nurse->id, 'code' => 'ENF-801', 'specialty' => 'Triage', 'status' => 'activo']);

        $this->auditor = User::factory()->create(['name' => 'Auditor Oficial', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->auditor->assignRole('auditor');

        $this->admin = User::factory()->create(['name' => 'Administrador General', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->admin->assignRole('administrador');

        $this->patient = Patient::create([
            'full_name' => 'Fernando Ospina',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1025444555',
            'document_index' => Patient::computeDocumentIndex('Cédula de ciudadanía', '1025444555'),
            'birth_date' => '1990-08-12',
            'phone' => '3201112233',
        ]);

        $this->episode = EmergencyEpisode::create([
            'patient_id' => $this->patient->id,
            'public_code' => 'UBTSEC01',
            'origin' => 'urgencias',
            'status' => 'ingresado',
            'reason' => 'Sospecha de intoxicación aguda',
            'responsible_user_id' => $this->doctor->id,
            'triage_level' => 'II',
            'arrived_at' => now()->subHours(2),
            'triage_at' => now()->subHours(2)->addMinutes(10),
            'admitted_at' => now()->subHour(),
        ]);
    }

    public function test_csrf_protection_rejects_post_requests_without_token(): void
    {
        $middleware = new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        $this->app->instance(ValidateCsrfToken::class, $middleware);

        $responseLogin = $this->withHeaders(['X-CSRF-TOKEN' => 'token-invalido'])
            ->post('/login', ['email' => 'test@x.test', 'password' => 'clave123']);
        $responseLogin->assertStatus(419);

        $responsePrereg = $this->withHeaders(['X-CSRF-TOKEN' => 'token-invalido'])
            ->post('/preregistro', ['full_name' => 'Ataque Bot']);
        $responsePrereg->assertStatus(419);

        $responseAcompanante = $this->withHeaders(['X-CSRF-TOKEN' => 'token-invalido'])
            ->post('/acompanante', ['code' => 'UBTSEC01', 'pin' => '1234']);
        $responseAcompanante->assertStatus(419);
    }

    public function test_rate_limiting_enforces_throttling_on_public_endpoints(): void
    {
        // Contacto (throttle: 5,1)
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contacto', [
                'name' => 'Usuario Test',
                'email' => 'user@hospital.test',
                'message' => 'Consulta de prueba sobre servicios',
            ]);
        }

        $this->post('/contacto', [
            'name' => 'Usuario Test',
            'email' => 'user@hospital.test',
            'message' => 'Consulta bloqueada por exceso',
        ])->assertStatus(429);

        // Pre-registro (throttle: 10,1)
        for ($i = 0; $i < 10; $i++) {
            $this->post('/preregistro', [
                'full_name' => "Paciente {$i}",
                'reason' => 'Motivo de prueba',
                'allergies_type' => 'ninguna',
            ]);
        }

        $this->post('/preregistro', [
            'full_name' => 'Paciente Bloqueado',
            'reason' => 'Motivo excedido',
            'allergies_type' => 'ninguna',
        ])->assertStatus(429);
    }

    public function test_idor_and_unauthorized_episode_actions_are_blocked(): void
    {
        // Médico no asignado intenta dar de alta a un paciente sin ser responsable -> 403
        $responseDischarge = $this->actingAs($this->otherDoctor)
            ->post(route('episodes.discharge', $this->episode), [
                'discharge_type' => 'alta_medica',
            ]);
        $responseDischarge->assertForbidden();

        // Enfermero intenta dar de alta a un paciente (episodios.egreso es exclusivo médico) -> 403
        $responseNurseDischarge = $this->actingAs($this->nurse)
            ->post(route('episodes.discharge', $this->episode), [
                'discharge_type' => 'alta_medica',
            ]);
        $responseNurseDischarge->assertForbidden();

        // Auditor intenta modificar habitaciones o camillas -> 403
        $site = Site::create(['code' => 'UBT-S99', 'name' => 'Sede Seguridad']);
        $floor = Floor::create(['site_id' => $site->id, 'number' => 1, 'name' => 'Piso 1']);
        $area = Area::create(['floor_id' => $floor->id, 'name' => 'Urgencias', 'type' => 'observacion']);
        $room = Room::create(['area_id' => $area->id, 'code' => 'H99', 'capacity' => 2]);

        $responseAuditorRooms = $this->actingAs($this->auditor)
            ->post(route('beds.store', $room), ['code' => 'CAM-TEST']);
        $responseAuditorRooms->assertForbidden();
    }

    public function test_sensitive_clinical_and_personal_fields_are_encrypted_in_database(): void
    {
        // 1. Verificar datos del paciente en la tabla 'patients'
        $rawPatient = DB::table('patients')->where('id', $this->patient->id)->first();
        $this->assertNotNull($rawPatient);

        // El texto plano jamás debe estar en la columna de la base de datos
        $this->assertNotEquals('Fernando Ospina', $rawPatient->full_name);
        $this->assertNotEquals('1025444555', $rawPatient->document_number);
        $this->assertNotEquals('3201112233', $rawPatient->phone);

        // Debe ser un payload cifrado por Laravel (JSON serializado con iv, value, mac)
        $this->assertStringContainsString('eyJpdiI6', $rawPatient->full_name);
        $this->assertStringContainsString('eyJpdiI6', $rawPatient->document_number);

        // El índice ciego debe ser un hash sha256 hexadecimal
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $rawPatient->document_index);
        $this->assertEquals(
            Patient::computeDocumentIndex('Cédula de ciudadanía', '1025444555'),
            $rawPatient->document_index
        );

        // 2. Verificar datos clínicos en 'episode_clinical_data'
        $clinicalData = EpisodeClinicalData::create([
            'episode_id' => $this->episode->id,
            'allergies_type' => 'si',
            'allergies_detail' => 'Alergia severa a Penicilina G y Cefalosporinas',
            'current_medication' => 'Losartán 50mg cada 12h y Enalapril 20mg',
        ]);

        $rawClinicalData = DB::table('episode_clinical_data')->where('id', $clinicalData->id)->first();
        $this->assertNotEquals('Alergia severa a Penicilina G y Cefalosporinas', $rawClinicalData->allergies_detail);
        $this->assertNotEquals('Losartán 50mg cada 12h y Enalapril 20mg', $rawClinicalData->current_medication);
        $this->assertStringContainsString('eyJpdiI6', $rawClinicalData->allergies_detail);

        // 3. Verificar evolución médica en 'clinical_entries'
        $entry = ClinicalEntry::create([
            'episode_id' => $this->episode->id,
            'author_user_id' => $this->doctor->id,
            'type' => 'diagnostico',
            'content' => 'Paciente con probable cuadro de intoxicación por organofosforados. Se inicia protocolo de atropinización.',
            'performed_at' => now(),
        ]);

        $rawEntry = DB::table('clinical_entries')->where('id', $entry->id)->first();
        $this->assertNotEquals('Paciente con probable cuadro de intoxicación por organofosforados. Se inicia protocolo de atropinización.', $rawEntry->content);
        $this->assertStringContainsString('eyJpdiI6', $rawEntry->content);

        // 4. Verificar datos del acompañante y PIN en 'companions'
        $companion = Companion::create([
            'episode_id' => $this->episode->id,
            'name' => 'María Ospina',
            'phone' => '3109998877',
            'pin' => '849201',
            'pin_hash' => Hash::make('849201'),
        ]);

        $rawCompanion = DB::table('companions')->where('id', $companion->id)->first();
        $this->assertNotEquals('3109998877', $rawCompanion->phone);
        $this->assertNotEquals('849201', $rawCompanion->pin);
        $this->assertTrue(Hash::check('849201', $rawCompanion->pin_hash));
    }

    public function test_authenticated_pages_enforce_no_store_cache_headers(): void
    {
        // Página pública: tiene cabeceras de seguridad generales, pero no no-store
        $publicResponse = $this->get('/');
        $publicResponse->assertHeader('X-Content-Type-Options', 'nosniff');
        $publicResponse->assertHeader('X-Frame-Options', 'DENY');

        // Página privada autenticada: DEBE tener no-store para no retener datos de salud en caché del navegador
        $privateResponse = $this->actingAs($this->doctor)->get(route('dashboard'));
        $privateResponse->assertHeader('Cache-Control');
        $this->assertStringContainsString('no-store', (string) $privateResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $privateResponse->headers->get('Cache-Control'));
        $privateResponse->assertHeader('Pragma', 'no-cache');
    }

    public function test_route_parameter_constraints_reject_malformed_identifiers(): void
    {
        // UUID malformado o inyección en parámetro {episode} -> 404
        $this->actingAs($this->doctor)
            ->get('/episodios/not-a-valid-uuid')
            ->assertNotFound();

        $this->actingAs($this->doctor)
            ->get('/episodios/123456')
            ->assertNotFound();

        // Parámetro numérico {user} con inyección de letras -> 404
        $this->actingAs($this->admin)
            ->get('/admin/usuarios/inyeccion-string/editar')
            ->assertNotFound();

        // Código de pre-registro con caracteres inválidos -> 404
        $this->get('/preregistro/exito/codigo-con-caracteres-invalidos!@#$')
            ->assertNotFound();
    }
}
