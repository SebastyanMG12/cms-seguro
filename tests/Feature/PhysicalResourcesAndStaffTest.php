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
            'name' => 'Hospital de Ubaté',
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

    public function test_staff_index_renders_successfully_with_shifts_and_metrics(): void
    {
        $admin = $this->user('administrador');
        $medico = $this->user('medico');
        app(StaffService::class)->createProfile($medico, 'Urgenciólogo');

        $response = $this->actingAs($admin)->get('/personal');

        $response->assertOk();
        $response->assertViewHas(['staff', 'shifts', 'availableUsers', 'totalStaff', 'activeShiftsCount']);
        $response->assertSee('Plantilla de Profesionales Clínicos');
        $response->assertSee('Programación de Turnos Clínicos');
    }

    public function test_admin_can_create_medical_staff_with_user_and_profile_directly(): void
    {
        $admin = $this->user('administrador');

        $response = $this->actingAs($admin)->post('/personal/crear-usuario', [
            'name' => 'Dr. Fernando Ruiz',
            'email' => 'fernando.ruiz@hospital.gov.co',
            'password' => 'SecurePass123*',
            'roles' => ['medico'],
            'specialty' => 'Urgenciólogo',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'fernando.ruiz@hospital.gov.co',
        ]);
        $user = User::where('email', 'fernando.ruiz@hospital.gov.co')->first();
        $this->assertTrue($user->hasRole('medico'));
        $this->assertNotNull($user->staffProfile);
        $this->assertSame('Urgenciólogo', $user->staffProfile->specialty);
        $this->assertStringStartsWith('MED-', $user->staffProfile->code);
    }

    public function test_admin_can_edit_medical_staff_profile_and_user_details(): void
    {
        $admin = $this->user('administrador');
        $medico = $this->user('medico');
        $profile = app(StaffService::class)->createProfile($medico, 'Médico General');

        $editView = $this->actingAs($admin)->get("/personal/{$profile->id}/editar");
        $editView->assertOk();
        $editView->assertSee('Editar Profesional Clínico');

        $updateResponse = $this->actingAs($admin)->put("/personal/{$profile->id}", [
            'name' => 'Dr. Médico Modificado',
            'email' => $medico->email,
            'specialty' => 'Especialista en Urgencias',
            'status' => 'en_descanso',
            'roles' => ['medico', 'coordinador'],
        ]);

        $updateResponse->assertRedirect(route('staff.index'));
        $this->assertSame('Dr. Médico Modificado', $medico->fresh()->name);
        $this->assertSame('Especialista en Urgencias', $profile->fresh()->specialty);
        $this->assertSame('en_descanso', $profile->fresh()->status);
        $this->assertTrue($medico->fresh()->hasRole('coordinador'));
    }

    public function test_shift_store_via_http_and_start(): void
    {
        $admin = $this->user('administrador');
        $medico = $this->user('medico');
        app(StaffService::class)->createProfile($medico, 'Cirujano');

        $storeResponse = $this->actingAs($admin)->post('/turnos', [
            'user_id' => $medico->id,
            'type' => 'rotativo',
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addHours(8)->format('Y-m-d H:i:s'),
        ]);

        $storeResponse->assertRedirect();
        $shift = Shift::where('user_id', $medico->id)->first();
        $this->assertNotNull($shift);
        $this->assertSame('programado', $shift->status);
        $this->assertSame('rotativo', $shift->type);

        $startResponse = $this->actingAs($admin)->post("/turnos/{$shift->id}/iniciar");
        $startResponse->assertRedirect();
        $this->assertSame('activo', $shift->fresh()->status);
    }

    public function test_inhabilitating_room_synchronizes_its_beds_to_fuera_de_servicio(): void
    {
        $admin = $this->user('administrador');
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $resourceService = app(PhysicalResourceService::class);
        $bed1 = $resourceService->createBed($room);

        $this->assertSame(Bed::STATUS_DISPONIBLE, $bed1->status);

        // Inhabilitar habitación vía HTTP
        $response = $this->actingAs($admin)->post("/habitaciones/{$room->id}/estado", [
            'status' => 'fuera_de_servicio',
        ]);

        $response->assertRedirect();
        $this->assertSame('fuera_de_servicio', $room->fresh()->status);
        $this->assertSame(Bed::STATUS_FUERA_DE_SERVICIO, $bed1->fresh()->status);

        // Habilitar de nuevo la habitación
        $this->actingAs($admin)->post("/habitaciones/{$room->id}/estado", [
            'status' => 'disponible',
        ]);
        $this->assertSame('disponible', $room->fresh()->status);
        $this->assertSame(Bed::STATUS_DISPONIBLE, $bed1->fresh()->status);
    }

    public function test_admin_can_update_room_capacity_and_add_bed(): void
    {
        $admin = $this->user('administrador');
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $room->update(['capacity' => 1]);

        $resourceService = app(PhysicalResourceService::class);
        $bed1 = $resourceService->createBed($room);

        // Intentar agregar cama superando capacidad falla
        $this->actingAs($admin)->post("/habitaciones/{$room->id}/camillas")
            ->assertSessionHasErrors('capacity');

        // Ampliar capacidad a 3
        $this->actingAs($admin)->put("/habitaciones/{$room->id}", [
            'capacity' => 3,
        ])->assertRedirect();

        $this->assertSame(3, $room->fresh()->capacity);

        // Ahora agregar camilla es exitoso
        $this->actingAs($admin)->post("/habitaciones/{$room->id}/camillas")
            ->assertRedirect();
        $this->assertCount(2, $room->fresh()->beds);
    }

    public function test_admin_can_delete_available_bed_and_add_another_bed(): void
    {
        $admin = $this->user('administrador');
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $resourceService = app(PhysicalResourceService::class);
        $bed1 = $resourceService->createBed($room);

        // Eliminar camilla
        $delResponse = $this->actingAs($admin)->delete("/camillas/{$bed1->id}");
        $delResponse->assertRedirect();
        $this->assertTrue($bed1->fresh()->trashed());

        // Agregar nueva camilla en el espacio liberado
        $addResponse = $this->actingAs($admin)->post("/habitaciones/{$room->id}/camillas");
        $addResponse->assertRedirect();

        // El conteo de camas activas sigue siendo 1
        $this->assertCount(1, $room->fresh()->beds);
    }

    public function test_cannot_delete_occupied_bed(): void
    {
        $admin = $this->user('administrador');
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $resourceService = app(PhysicalResourceService::class);
        $bed1 = $resourceService->createBed($room);

        $bed1->update(['status' => Bed::STATUS_OCUPADA]);

        $this->expectException(ValidationException::class);
        $resourceService->deleteBed($bed1);
    }

    public function test_cannot_put_room_into_maintenance_or_fuera_de_servicio_if_bed_is_occupied(): void
    {
        $admin = $this->user('administrador');
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $resourceService = app(PhysicalResourceService::class);
        $bed1 = $resourceService->createBed($room);
        $bed1->update(['status' => Bed::STATUS_OCUPADA]);

        // Intentar poner en mantenimiento debe fallar con error en la sesión
        $response = $this->actingAs($admin)->post("/habitaciones/{$room->id}/estado", [
            'status' => 'mantenimiento',
        ]);
        $response->assertSessionHasErrors('status');
        $this->assertSame('disponible', $room->fresh()->status);

        // Intentar poner fuera de servicio también debe fallar
        $responseFds = $this->actingAs($admin)->post("/habitaciones/{$room->id}/estado", [
            'status' => 'fuera_de_servicio',
        ]);
        $responseFds->assertSessionHasErrors('status');
        $this->assertSame('disponible', $room->fresh()->status);
    }

    public function test_putting_room_in_maintenance_cascades_beds_to_mantenimiento(): void
    {
        $admin = $this->user('administrador');
        $hierarchy = $this->createHospitalHierarchy();
        $room = $hierarchy['room'];
        $resourceService = app(PhysicalResourceService::class);
        $bed1 = $resourceService->createBed($room);

        $this->assertSame(Bed::STATUS_DISPONIBLE, $bed1->status);

        $response = $this->actingAs($admin)->post("/habitaciones/{$room->id}/estado", [
            'status' => 'mantenimiento',
        ]);
        $response->assertRedirect();
        $this->assertSame('mantenimiento', $room->fresh()->status);
        $this->assertSame(Bed::STATUS_MANTENIMIENTO, $bed1->fresh()->status);
    }
}
