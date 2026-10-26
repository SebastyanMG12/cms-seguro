<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Floor;
use App\Models\Room;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PhysicalResourceService
{
    public function createSite(array $data): Site
    {
        return DB::transaction(function () use ($data): Site {
            $site = Site::create($data);
            Audit::log('site_created', $site);

            return $site;
        });
    }

    public function createFloor(Site $site, array $data): Floor
    {
        return DB::transaction(function () use ($site, $data): Floor {
            $floor = $site->floors()->create($data);
            Audit::log('floor_created', $floor);

            return $floor;
        });
    }

    public function createArea(Floor $floor, array $data): Area
    {
        return DB::transaction(function () use ($floor, $data): Area {
            $area = $floor->areas()->create($data);
            Audit::log('area_created', $area);

            return $area;
        });
    }

    public function createRoom(Area $area, array $data): Room
    {
        return DB::transaction(function () use ($area, $data): Room {
            $room = $area->rooms()->create($data);
            Audit::log('room_created', $room);

            return $room;
        });
    }

    public function updateRoomStatus(Room $room, string $newStatus): Room
    {
        if (in_array($newStatus, ['fuera_de_servicio', 'mantenimiento'], true) && $room->beds()->where('status', Bed::STATUS_OCUPADA)->exists()) {
            $statusText = $newStatus === 'mantenimiento' ? 'en mantenimiento' : 'fuera de servicio';
            throw ValidationException::withMessages([
                'status' => "No se puede poner {$statusText} la habitación {$room->code} porque tiene pacientes en camilla. Debe reubicarlos primero.",
            ]);
        }

        return DB::transaction(function () use ($room, $newStatus): Room {
            $oldStatus = $room->status;
            $room->forceFill(['status' => $newStatus])->save();

            // Sincronizar camillas según requerimiento:
            // Cuando se inhabilita o se pone en mantenimiento una habitación, se sincronizan sus camillas
            if ($newStatus === 'fuera_de_servicio') {
                $room->beds()->update(['status' => Bed::STATUS_FUERA_DE_SERVICIO]);
            } elseif ($newStatus === 'mantenimiento') {
                $room->beds()->update(['status' => Bed::STATUS_MANTENIMIENTO]);
            } elseif ($newStatus === 'disponible') {
                $room->beds()->whereIn('status', [Bed::STATUS_FUERA_DE_SERVICIO, Bed::STATUS_MANTENIMIENTO])
                    ->update(['status' => Bed::STATUS_DISPONIBLE]);
            }

            Audit::log('room_status_changed', $room, [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            return $room;
        });
    }

    public function updateRoom(Room $room, array $data): Room
    {
        return DB::transaction(function () use ($room, $data): Room {
            if (isset($data['capacity'])) {
                $currentBedsCount = $room->beds()->count();
                if ($data['capacity'] < $currentBedsCount) {
                    throw ValidationException::withMessages([
                        'capacity' => "La capacidad ({$data['capacity']}) no puede ser menor al número actual de camillas ({$currentBedsCount}).",
                    ]);
                }
                $room->capacity = (int) $data['capacity'];
            }

            if (isset($data['status'])) {
                $room->status = $data['status'];
            }

            $room->save();

            Audit::log('room_updated', $room, ['capacity' => $room->capacity]);

            return $room;
        });
    }

    public function generateBedCode(Room $room): string
    {
        $floorNumber = $room->area?->floor?->number ?? 1;
        $roomCode = $room->code;
        $existingCount = Bed::withTrashed()->where('room_id', $room->id)->count();

        do {
            $letter = chr(65 + ($existingCount % 26));
            if ($existingCount >= 26) {
                $letter .= (int) floor($existingCount / 26);
            }
            $code = sprintf('P%s-%s-%s', $floorNumber, $roomCode, $letter);
            $existingCount++;
        } while (Bed::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function createBed(Room $room): Bed
    {
        if ($room->beds()->count() >= $room->capacity) {
            throw ValidationException::withMessages([
                'capacity' => "La habitación {$room->code} ya alcanzó su capacidad máxima de {$room->capacity} camillas.",
            ]);
        }

        return DB::transaction(function () use ($room): Bed {
            $code = $this->generateBedCode($room);

            $bed = $room->beds()->create([
                'code' => $code,
                'status' => Bed::STATUS_DISPONIBLE,
            ]);

            Audit::log('bed_created', $bed, ['code' => $code]);

            return $bed;
        });
    }

    public function deleteBed(Bed $bed): void
    {
        if ($bed->status === Bed::STATUS_OCUPADA || $bed->activeAssignment()->exists()) {
            throw ValidationException::withMessages([
                'bed' => "No se puede eliminar la camilla {$bed->code} porque está ocupada por un paciente.",
            ]);
        }

        DB::transaction(function () use ($bed): void {
            $bedCode = $bed->code;
            $bed->delete();

            Audit::log('bed_deleted', $bed, ['code' => $bedCode]);
        });
    }

    public function updateBedStatus(Bed $bed, string $newStatus): Bed
    {
        if ($bed->status === Bed::STATUS_OCUPADA && $newStatus !== Bed::STATUS_OCUPADA) {
            throw ValidationException::withMessages([
                'status' => 'No se puede cambiar el estado de una camilla ocupada sin liberar la asignación previamente.',
            ]);
        }

        return DB::transaction(function () use ($bed, $newStatus): Bed {
            $oldStatus = $bed->status;
            $bed->forceFill(['status' => $newStatus])->save();

            Audit::log('bed_status_changed', $bed, [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            return $bed;
        });
    }

    public function assignBed(Bed $bed, ?string $episodeId = null): BedAssignment
    {
        return DB::transaction(function () use ($bed, $episodeId): BedAssignment {
            /** @var Bed $lockedBed */
            $lockedBed = Bed::where('id', $bed->id)->lockForUpdate()->firstOrFail();

            if (! $lockedBed->isAssignable()) {
                throw ValidationException::withMessages([
                    'bed' => "La camilla {$lockedBed->code} no está disponible para asignación.",
                ]);
            }

            $assignment = $lockedBed->assignments()->create([
                'episode_id' => $episodeId,
                'assigned_at' => now(),
            ]);

            $lockedBed->forceFill(['status' => Bed::STATUS_OCUPADA])->save();

            Audit::log('bed_assigned', $lockedBed, [
                'assignment_id' => $assignment->id,
                'episode_id' => $episodeId,
            ]);

            return $assignment;
        });
    }

    public function releaseBed(Bed $bed, string $nextStatus = Bed::STATUS_EN_LIMPIEZA): void
    {
        DB::transaction(function () use ($bed, $nextStatus): void {
            /** @var Bed $lockedBed */
            $lockedBed = Bed::where('id', $bed->id)->lockForUpdate()->firstOrFail();

            $activeAssignment = $lockedBed->activeAssignment()->lockForUpdate()->first();
            if ($activeAssignment) {
                $activeAssignment->update(['released_at' => now()]);
            }

            $lockedBed->forceFill(['status' => $nextStatus])->save();

            Audit::log('bed_released', $lockedBed, [
                'next_status' => $nextStatus,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function getOccupancyReport(): array
    {
        $totalBeds = Bed::count();
        $bedsByStatus = Bed::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $rooms = Room::with('beds')->get();
        $fullRoomsCount = $rooms->filter(fn (Room $room) => $room->isFull())->count();

        return [
            'total_beds' => $totalBeds,
            'beds_by_status' => $bedsByStatus,
            'total_rooms' => $rooms->count(),
            'full_rooms' => $fullRoomsCount,
            'rooms' => $rooms,
        ];
    }
}
