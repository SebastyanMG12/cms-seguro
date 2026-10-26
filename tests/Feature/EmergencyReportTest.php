<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ClinicalEntry;
use App\Models\EmergencyEpisode;
use App\Models\Patient;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\EmergencyMetricsService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmergencyReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $coordinator;

    protected User $auditor;

    protected User $unauthorizedUser;

    protected User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Coordinador con permiso reportes.ver y auditoria.ver
        $this->coordinator = User::factory()->create(['name' => 'Coordinador Médico', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->coordinator->assignRole('coordinador');

        // Auditor
        $this->auditor = User::factory()->create(['name' => 'Auditor de Calidad', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->auditor->assignRole('auditor');

        // Médico
        $this->doctor = User::factory()->create(['name' => 'Dr. Camilo Torres', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->doctor->assignRole('medico');
        StaffProfile::create([
            'user_id' => $this->doctor->id,
            'code' => 'MED-550',
            'specialty' => 'Urgencias',
            'status' => 'activo',
        ]);

        // Usuario no autorizado (sin reportes.ver)
        $this->unauthorizedUser = User::factory()->create(['name' => 'Auxiliar Administrativo', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->unauthorizedUser->assignRole('admisiones');
    }

    public function test_guest_cannot_access_reports_dashboard(): void
    {
        $response = $this->get(route('reports.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_access_reports(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('reports.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_reports_dashboard(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Tablero de Tiempos de Espera por Nivel de Triage');
        $response->assertSee('Resolución 5596 de 2015');
        $response->assertSee('Triage I');
        $response->assertSee('Triage II');
        $response->assertSee('Triage III');
        $response->assertSee('Triage IV');
        $response->assertSee('Triage V');
    }

    public function test_metrics_service_calculates_real_wait_times_and_compliance(): void
    {
        $targetDate = Carbon::parse('2026-10-25 12:00:00');

        $patient1 = Patient::create([
            'full_name' => 'Carlos Mendoza',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1025111222',
            'document_index' => Patient::computeDocumentIndex('Cédula de ciudadanía', '1025111222'),
        ]);

        $patient2 = Patient::create([
            'full_name' => 'Diana Gómez',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1025333444',
            'document_index' => Patient::computeDocumentIndex('Cédula de ciudadanía', '1025333444'),
        ]);

        // Paciente 1: Triage II - Esperó 20 min (meta 30 min -> CUMPLE)
        $episode1 = EmergencyEpisode::create([
            'patient_id' => $patient1->id,
            'public_code' => 'UBTCAR01',
            'origin' => 'urgencias',
            'status' => 'ingresado',
            'reason' => 'Dolor precordial agudo',
            'triage_level' => 'II',
            'arrived_at' => $targetDate->copy()->subMinutes(60),
            'triage_at' => $targetDate->copy()->subMinutes(50),
        ]);

        ClinicalEntry::create([
            'episode_id' => $episode1->id,
            'author_user_id' => $this->doctor->id,
            'type' => 'diagnostico',
            'content' => 'Evaluación inicial paciente 1',
            'performed_at' => $targetDate->copy()->subMinutes(30), // 20 min después del triaje
        ]);

        // Paciente 2: Triage II - Esperó 50 min (meta 30 min -> EXCEDE META)
        $episode2 = EmergencyEpisode::create([
            'patient_id' => $patient2->id,
            'public_code' => 'UBTDIA02',
            'origin' => 'urgencias',
            'status' => 'ingresado',
            'reason' => 'Cefalea intensa y mareo',
            'triage_level' => 'II',
            'arrived_at' => $targetDate->copy()->subMinutes(90),
            'triage_at' => $targetDate->copy()->subMinutes(80),
        ]);

        ClinicalEntry::create([
            'episode_id' => $episode2->id,
            'author_user_id' => $this->doctor->id,
            'type' => 'diagnostico',
            'content' => 'Evaluación inicial paciente 2',
            'performed_at' => $targetDate->copy()->subMinutes(30), // 50 min después del triaje
        ]);

        $service = app(EmergencyMetricsService::class);
        $metrics = $service->getTriageWaitTimeMetrics($targetDate);

        $this->assertArrayHasKey('II', $metrics);
        $triageII = $metrics['II'];

        $this->assertEquals(2, $triageII['total_patients']);
        $this->assertEquals(35, $triageII['avg_wait_minutes']); // (20 + 50) / 2 = 35
        $this->assertEquals(50, $triageII['max_wait_minutes']);
        $this->assertEquals(50.0, $triageII['compliance_rate']); // 1 de 2 dentro de los 30 min
    }

    public function test_date_filter_on_reports(): void
    {
        $response = $this->actingAs($this->coordinator)
            ->get(route('reports.index', ['date' => '2026-10-24']));

        $response->assertOk();
        $response->assertSee('2026-10-24');
    }

    public function test_auditor_can_view_audit_dashboard_with_clinical_filter(): void
    {
        AuditLog::create([
            'user_id' => $this->doctor->id,
            'event' => 'episodio.lectura',
            'model_type' => 'EmergencyEpisode',
            'model_id' => 999,
            'ip_address' => '127.0.0.1',
            'metadata' => ['public_code' => 'UBTAUD01'],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->auditor)
            ->get(route('audit.index', ['view_mode' => 'clinical']));

        $response->assertOk();
        $response->assertSee('Auditoría Forense e Inmutable');
        $response->assertSee('episodio.lectura');
        $response->assertSee('UBTAUD01');
    }

    public function test_auditor_can_export_audit_log_to_csv(): void
    {
        AuditLog::create([
            'user_id' => $this->coordinator->id,
            'event' => 'episodio.pdf_generado',
            'model_type' => 'EmergencyEpisode',
            'model_id' => 888,
            'ip_address' => '192.168.1.15',
            'metadata' => [
                'public_code' => 'UBTPDF99',
                'patient_identifier' => '1025888999',
            ],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->auditor)
            ->get(route('audit.index', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('episodio.pdf_generado', $content);
        $this->assertStringContainsString('UBTPDF99', $content);
        $this->assertStringContainsString('192.168.1.15', $content);
    }
}
