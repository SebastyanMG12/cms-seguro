<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Site;
use App\Services\PhysicalResourceService;
use Illuminate\Database\Seeder;

class HospitalInfrastructureSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PhysicalResourceService::class);

        // 1. Sede Principal
        $site = Site::firstOrCreate(
            ['code' => 'HESU'],
            [
                'name' => 'Hospital El Salvador de Ubaté - Sede Principal',
                'address' => 'Carrera 7 # 8-45, Ubaté, Cundinamarca',
                'status' => 'activo',
            ]
        );

        // 2. Piso 1
        $floor = Floor::firstOrCreate(
            ['site_id' => $site->id, 'number' => 1],
            ['name' => 'Planta Principal de Urgencias']
        );

        // 3. Área de Urgencias
        $area = Area::firstOrCreate(
            ['floor_id' => $floor->id, 'name' => 'Servicio de Urgencias'],
            ['type' => 'urgencias']
        );

        // 4. Habitaciones de Urgencias
        $roomsData = [
            [
                'code' => 'OBS1',
                'capacity' => 4,
                'is_isolation' => false,
                'is_pediatric' => false,
                'status' => 'disponible',
            ],
            [
                'code' => 'REA1',
                'capacity' => 2,
                'is_isolation' => false,
                'is_pediatric' => false,
                'status' => 'disponible',
            ],
            [
                'code' => 'PED1',
                'capacity' => 2,
                'is_isolation' => false,
                'is_pediatric' => true,
                'status' => 'disponible',
            ],
            [
                'code' => 'AIS1',
                'capacity' => 1,
                'is_isolation' => true,
                'is_pediatric' => false,
                'status' => 'disponible',
            ],
        ];

        foreach ($roomsData as $rData) {
            $room = Room::firstOrCreate(
                ['area_id' => $area->id, 'code' => $rData['code']],
                $rData
            );

            // Crear camillas hasta completar la capacidad si no existen
            $currentBeds = $room->beds()->count();
            for ($i = $currentBeds; $i < $room->capacity; $i++) {
                $service->createBed($room);
            }
        }
    }
}
