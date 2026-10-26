<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bed extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DISPONIBLE = 'disponible';

    public const STATUS_OCUPADA = 'ocupada';

    public const STATUS_EN_LIMPIEZA = 'en_limpieza';

    public const STATUS_MANTENIMIENTO = 'mantenimiento';

    public const STATUS_FUERA_DE_SERVICIO = 'fuera_de_servicio';

    public const STATUS_RESERVADA = 'reservada';

    protected $fillable = [
        'room_id',
        'code',
        'status',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BedAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(BedAssignment::class)->whereNull('released_at');
    }

    /**
     * Una camilla es asignable si está en estado 'disponible', su habitación está 'disponible'
     * y su área se encuentra activa.
     */
    public function isAssignable(): bool
    {
        if ($this->status !== self::STATUS_DISPONIBLE) {
            return false;
        }

        $room = $this->room;
        if (! $room || ! $room->isAvailable()) {
            return false;
        }

        $area = $room->area;
        if (! $area || ! $area->isActive()) {
            return false;
        }

        return true;
    }
}
