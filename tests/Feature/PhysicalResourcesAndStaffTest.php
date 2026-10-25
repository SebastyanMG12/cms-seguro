<?php

namespace Tests\Feature;

use App\Models\Bed;
use App\Models\Shift;
use App\Models\Site;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\PhysicalResourceService;
use App\Services\StaffService;
use App\Services\TwoFactor;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhysicalResourcesAndStaffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(string $role = 'autor', string $status = 'active', bool $withTwoFactor = true): User
    {
        $user = User::factory()->create();
        $user->forceFill(['status' => $status])->save();
        $user->assignRole($role);

        if ($withTwoFactor) {
            $user->forceFill([
                'two_factor_secret' => app(TwoFactor::class)->generateSecret(),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $user;
    }

    private function createHospitalHierarchy(): array
    {
        $site = Site::create([
            'name' => 'Hospital El Salvador de Ubaté',
            'code' => 'UBATE-CENTRAL',
            'address' => 'Carrera 7 # 8-45',
            'status' => 'activo',
        ]);

        $floor = $site->floors()->create([
            'number' => 1,
            'name' => 'Planta Principal',
        ]);

        $area = $floor->areas()->create([
            'name' => 'Urgencias Adultos',
            'type' => 'urgencias',
            'status' => 'activo',
        ]);

        $room = $area->rooms()->create([
            'code' => 'H101',
            'capacity' => 2,
            'status' => 'disponible',
            'is_isolation' => false,
            'is_pediatric' => false,
        ]);

        return compact('site', 'floor', 'area', 'room');
    }

    public function test_bed_code_is_generated_and_capacity_is_enforced(): void
    {
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $service = app(PhysicalResourceService::class);

        $bed1 = $service->createBed($room);
        $this->assertSame('P1-H101-A', $bed1->code);
        $this->assertSame(Bed::STATUS_DISPONIBLE, $bed1->status);

        $bed2 = $service->createBed($room);
        $this->assertSame('P1-H101-B', $bed2->code);

        $this->expectException(ValidationException::class);
        $service->createBed($room); // Supera capacidad configurada de 2
    }

    public function test_room_is_full_calculation(): void
    {
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $service = app(PhysicalResourceService::class);

        $bed1 = $service->createBed($room);
        $bed2 = $service->createBed($room);

        $this->assertFalse($room->isFull());

        $service->updateBedStatus($bed1, Bed::STATUS_OCUPADA);
        $this->assertFalse($room->isFull());

        // Si la segunda cama pasa a mantenimiento, sólo queda 1 cama habilitada y está ocupada => habitación llena
        $service->updateBedStatus($bed2, Bed::STATUS_MANTENIMIENTO);
        $this->assertTrue($room->isFull());

        // Si la segunda cama vuelve a estar disponible, ya no está llena
        $service->updateBedStatus($bed2, Bed::STATUS_DISPONIBLE);
        $this->assertFalse($room->isFull());

        // Si la segunda cama pasa a ocupada, ambas están ocupadas => habitación llena
        $service->updateBedStatus($bed2, Bed::STATUS_OCUPADA);
        $this->assertTrue($room->isFull());
    }

    public function test_cannot_assign_unavailable_bed(): void
    {
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $service = app(PhysicalResourceService::class);

        $bed = $service->createBed($room);
        $service->updateBedStatus($bed, Bed::STATUS_FUERA_DE_SERVICIO);

        $this->assertFalse($bed->isAssignable());

        $this->expectException(ValidationException::class);
        $service->assignBed($bed, '00000000-0000-0000-0000-000000000001');
    }

    public function test_successful_bed_assignment_and_release(): void
    {
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $service = app(PhysicalResourceService::class);

        $bed = $service->createBed($room);
        $this->assertTrue($bed->isAssignable());

        $assignment = $service->assignBed($bed, '00000000-0000-0000-0000-000000000001');

        $bed->refresh();
        $this->assertSame(Bed::STATUS_OCUPADA, $bed->status);
        $this->assertFalse($bed->isAssignable());
        $this->assertNull($assignment->released_at);

        $service->releaseBed($bed);

        $bed->refresh();
        $this->assertSame(Bed::STATUS_EN_LIMPIEZA, $bed->status);
        $this->assertNotNull($assignment->fresh()->released_at);
    }

    public function test_database_partial_unique_index_prevents_active_double_assignment(): void
    {
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $service = app(PhysicalResourceService::class);

        $bed = $service->createBed($room);
        $service->assignBed($bed, '00000000-0000-0000-0000-000000000001');

        // Intento de forzar doble asignación a nivel de BD sin liberar la anterior
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('bed_assignments')->insert([
            'bed_id' => $bed->id,
            'episode_id' => '00000000-0000-0000-0000-000000000002',
            'assigned_at' => now(),
            'released_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cannot_put_room_out_of_service_if_beds_are_occupied(): void
    {
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $service = app(PhysicalResourceService::class);

        $bed = $service->createBed($room);
        $service->assignBed($bed);

        $this->expectException(ValidationException::class);
        $service->updateRoomStatus($room, 'fuera_de_servicio');
    }

    public function test_role_authorization_for_occupancy_and_beds(): void
    {
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $bed = app(PhysicalResourceService::class)->createBed($room);

        $autor = $this->user('autor');
        $medico = $this->user('medico');
        $coordinador = $this->user('coordinador');
        $admin = $this->user('administrador');

        // Autor no puede ver ocupación
        $this->actingAs($autor)->get('/ocupacion')->assertForbidden();

        // Médico puede ver ocupación pero no cambiar estado de camilla
        $this->actingAs($medico)->get('/ocupacion')->assertOk();
        $this->actingAs($medico)->post("/camillas/{$bed->id}/estado", ['status' => 'mantenimiento'])->assertForbidden();

        // Coordinador puede cambiar estado de camilla
        $this->actingAs($coordinador)->post("/camillas/{$bed->id}/estado", ['status' => 'mantenimiento'])
            ->assertRedirect();
        $this->assertSame(Bed::STATUS_MANTENIMIENTO, $bed->fresh()->status);

        // Administrador puede agregar camillas
        $this->actingAs($admin)->post("/habitaciones/{$room->id}/camillas")->assertRedirect();
        $this->assertCount(2, $room->beds);
    }

    public function test_staff_profile_and_status_readiness(): void
    {
        $medicoUser = $this->user('medico');
        $staffService = app(StaffService::class);

        $profile = $staffService->createProfile($medicoUser, 'Urgenciólogo');

        $this->assertStringStartsWith('MED-', $profile->code);
        $this->assertSame(StaffProfile::STATUS_ACTIVO, $profile->status);
        $this->assertTrue($profile->isAssignable());

        // Poner en descanso
        $staffService->updateStatus($profile, StaffProfile::STATUS_EN_DESCANSO);
        $this->assertFalse($profile->isAssignable());

        // Reactivar
        $staffService->updateStatus($profile, StaffProfile::STATUS_ACTIVO);
        $this->assertTrue($profile->isAssignable());

        // Borrado lógico
        $staffService->deleteProfile($profile);
        $this->assertTrue($profile->trashed());
        $this->assertFalse($profile->isAssignable());
    }

    public function test_shifts_lifecycle_and_pending_episodes_validation(): void
    {
        $medicoUser = $this->user('medico');
        $staffService = app(StaffService::class);
        $staffService->createProfile($medicoUser, 'Médico General');

        $shift = $staffService->createShift($medicoUser, [
            'starts_at' => now(),
            'ends_at' => now()->addHours(12),
            'type' => 'diurno',
        ]);

        $this->assertSame(Shift::STATUS_PROGRAMADO, $shift->status);

        $staffService->startShift($shift);
        $this->assertSame(Shift::STATUS_ACTIVO, $shift->fresh()->status);

        // No se puede finalizar turno si hay pacientes asignados pendientes de reasignación
        try {
            $staffService->endShift($shift, 3);
            $this->fail('Se esperaba ValidationException por pacientes asignados al finalizar turno.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('shift', $e->errors());
        }

        // Finalizar con 0 pacientes asignados
        $staffService->endShift($shift, 0);
        $this->assertSame(Shift::STATUS_FINALIZADO, $shift->fresh()->status);
    }

    public function test_audit_logs_record_all_physical_resource_and_staff_events(): void
    {
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $resourceService = app(PhysicalResourceService::class);
        $staffService = app(StaffService::class);

        $bed = $resourceService->createBed($room);
        $this->assertDatabaseHas('audit_logs', ['event' => 'bed_created']);

        $resourceService->updateBedStatus($bed, Bed::STATUS_MANTENIMIENTO);
        $this->assertDatabaseHas('audit_logs', ['event' => 'bed_status_changed']);

        $resourceService->updateBedStatus($bed, Bed::STATUS_DISPONIBLE);
        $resourceService->assignBed($bed, '00000000-0000-0000-0000-000000000001');
        $this->assertDatabaseHas('audit_logs', ['event' => 'bed_assigned']);

        $resourceService->releaseBed($bed);
        $this->assertDatabaseHas('audit_logs', ['event' => 'bed_released']);

        $medico = $this->user('medico');
        $profile = $staffService->createProfile($medico, 'Triage');
        $this->assertDatabaseHas('audit_logs', ['event' => 'staff_profile_created']);

        $staffService->updateStatus($profile, StaffProfile::STATUS_EN_DESCANSO);
        $this->assertDatabaseHas('audit_logs', ['event' => 'staff_status_changed']);
    }
}
