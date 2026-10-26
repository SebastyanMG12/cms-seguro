<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Bed;
use App\Models\ClinicalEntry;
use App\Models\EmergencyEpisode;
use App\Models\Floor;
use App\Models\Patient;
use App\Models\Room;
use App\Models\Shift;
use App\Models\Site;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EpisodePdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $doctor;

    protected User $otherDoctor;

    protected User $coordinator;

    protected User $administrator;

    protected User $unauthorizedStaff;

    protected EmergencyEpisode $episode;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Crear médico 1
        $this->doctor = User::factory()->create(['name' => 'Dr. Alejandro Morales', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->doctor->assignRole('medico');
        StaffProfile::create([
            'user_id' => $this->doctor->id,
            'code' => 'MED-101',
            'specialty' => 'Urgenciología',
            'status' => 'activo',
        ]);
        Shift::create([
            'user_id' => $this->doctor->id,
            'type' => 'rotativo',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->addHours(6),
            'status' => 'activo',
        ]);

        // Crear médico 2 (no asignado)
        $this->otherDoctor = User::factory()->create(['name' => 'Dra. Beatriz Santos', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->otherDoctor->assignRole('medico');
        StaffProfile::create([
            'user_id' => $this->otherDoctor->id,
            'code' => 'MED-102',
            'specialty' => 'Medicina Interna',
            'status' => 'activo',
        ]);

        // Coordinador y Administrador
        $this->coordinator = User::factory()->create(['name' => 'Dra. Claudia Coordinadora', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->coordinator->assignRole('coordinador');

        $this->administrator = User::factory()->create(['name' => 'Ing. Daniel Administrador', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->administrator->assignRole('administrador');

        // Personal sin permiso para PDF
        $this->unauthorizedStaff = User::factory()->create(['name' => 'Recepcionista Turno', 'status' => 'active', 'two_factor_confirmed_at' => now()]);
        $this->unauthorizedStaff->assignRole('admisiones');

        // Infraestructura
        $site = Site::create(['code' => 'UBT-S1', 'name' => 'Hospital de Ubaté']);
        $floor = Floor::create(['site_id' => $site->id, 'number' => 1, 'name' => 'Piso Urgencias']);
        $area = Area::create(['floor_id' => $floor->id, 'name' => 'Observación Adultos', 'type' => 'observacion']);
        $room = Room::create(['area_id' => $area->id, 'code' => 'H101', 'capacity' => 2]);
        $bed = Bed::create(['room_id' => $room->id, 'code' => 'C-101', 'status' => 'disponible']);

        // Paciente
        $this->patient = Patient::create([
            'full_name' => 'Guillermo Pérez',
            'document_type' => 'Cédula de ciudadanía',
            'document_number' => '1025999888',
            'document_index' => Patient::computeDocumentIndex('Cédula de ciudadanía', '1025999888'),
            'birth_date' => '1985-06-15',
            'phone' => '3157778899',
        ]);

        // Episodio clínico en valoración
        $this->episode = EmergencyEpisode::create([
            'patient_id' => $this->patient->id,
            'public_code' => 'UBT998877',
            'origin' => 'urgencias',
            'status' => 'ingresado',
            'reason' => 'Dolor torácico opresivo de 1 hora de evolución con diaforesis',
            'responsible_user_id' => $this->doctor->id,
            'triage_level' => 'II',
            'arrived_at' => now()->subHours(3),
            'triage_at' => now()->subHours(3)->addMinutes(10),
            'admitted_at' => now()->subHours(2),
        ]);

        // Entrada clínica médica
        ClinicalEntry::create([
            'episode_id' => $this->episode->id,
            'author_user_id' => $this->doctor->id,
            'type' => 'diagnostico',
            'content' => 'Sospecha de síndrome coronario agudo sin elevación del segmento ST (CIE-10: I200 - Angina inestable). Se solicitan troponinas y electrocardiograma seriado.',
            'performed_at' => now()->subHours(2)->addMinutes(15),
        ]);
    }

    public function test_guest_cannot_download_or_stream_pdf(): void
    {
        $responseDownload = $this->get(route('episodes.pdf.download', $this->episode));
        $responseDownload->assertRedirect(route('login'));

        $responseStream = $this->get(route('episodes.pdf.stream', $this->episode));
        $responseStream->assertRedirect(route('login'));
    }

    public function test_staff_without_pdf_permission_cannot_download_pdf(): void
    {
        $response = $this->actingAs($this->unauthorizedStaff)
            ->get(route('episodes.pdf.download', $this->episode));

        $response->assertForbidden();
    }

    public function test_doctor_not_assigned_and_without_breakglass_cannot_download_pdf(): void
    {
        $response = $this->actingAs($this->otherDoctor)
            ->get(route('episodes.pdf.download', $this->episode));

        $response->assertForbidden();
    }

    public function test_assigned_doctor_can_download_and_stream_pdf_with_watermark_and_audit(): void
    {
        // Descarga de PDF
        $response = $this->actingAs($this->doctor)
            ->get(route('episodes.pdf.download', $this->episode));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
        $this->assertStringContainsString($this->episode->public_code, $response->headers->get('content-disposition'));

        // Verificar contenido binario PDF (magic header)
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        // Verificar registro de auditoría forense inmutable
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'episodio.pdf_generado',
            'user_id' => $this->doctor->id,
            'model_type' => 'EmergencyEpisode',
            'model_id' => $this->episode->id,
        ]);

        $auditLog = AuditLog::where('event', 'episodio.pdf_generado')
            ->where('model_id', $this->episode->id)
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->episode->public_code, $auditLog->metadata['public_code']);
        $this->assertEquals($this->patient->document_number, $auditLog->metadata['patient_identifier']);
        $this->assertEquals($this->doctor->name, $auditLog->metadata['generated_by_name']);

        // Visualización stream en navegador
        $streamResponse = $this->actingAs($this->doctor)
            ->get(route('episodes.pdf.stream', $this->episode));

        $streamResponse->assertOk();
        $streamResponse->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline;', $streamResponse->headers->get('content-disposition'));
    }

    public function test_doctor_with_active_breakglass_session_can_download_pdf(): void
    {
        $response = $this->actingAs($this->otherDoctor)
            ->withSession(["exceptional_access_{$this->episode->id}" => 'Atención médica de urgencia vital no programada'])
            ->get(route('episodes.pdf.download', $this->episode));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'episodio.pdf_generado',
            'user_id' => $this->otherDoctor->id,
        ]);
    }

    public function test_coordinator_and_administrator_can_download_pdf_for_any_episode(): void
    {
        // Coordinador
        $coordResponse = $this->actingAs($this->coordinator)
            ->get(route('episodes.pdf.download', $this->episode));

        $coordResponse->assertOk();
        $coordResponse->assertHeader('content-type', 'application/pdf');

        // Administrador
        $adminResponse = $this->actingAs($this->administrator)
            ->get(route('episodes.pdf.download', $this->episode));

        $adminResponse->assertOk();
        $adminResponse->assertHeader('content-type', 'application/pdf');
    }

    public function test_nn_episode_pdf_renders_successfully(): void
    {
        $nnEpisode = EmergencyEpisode::create([
            'patient_id' => null,
            'public_code' => 'UBTNN9911',
            'origin' => 'ambulancia',
            'status' => 'en_valoracion',
            'is_nn' => true,
            'nn_code' => 'NN-UBT-20261025-01',
            'reason' => 'Paciente encontrado inconsciente en vía pública',
            'estimated_age' => '45',
            'apparent_sex' => 'masculino',
            'distinguishing_marks' => 'Tatuaje en antebrazo derecho',
            'responsible_user_id' => $this->doctor->id,
            'arrived_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->doctor)
            ->get(route('episodes.pdf.download', $nnEpisode));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'episodio.pdf_generado',
            'model_id' => $nnEpisode->id,
            'metadata->patient_identifier' => 'NN-UBT-20261025-01',
        ]);
    }

    public function test_closed_episode_pdf_can_be_downloaded_for_legal_custody(): void
    {
        $this->episode->update([
            'status' => 'egresado',
            'discharged_at' => now(),
            'discharge_type' => 'alta_medica',
            'discharge_notes' => 'Paciente egresa estable con orden de control por cardiología externa.',
        ]);

        $response = $this->actingAs($this->doctor)
            ->get(route('episodes.pdf.download', $this->episode));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
