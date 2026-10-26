<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'area_id',
        'code',
        'capacity',
        'status',
        'is_isolation',
        'is_pediatric',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_isolation' => 'boolean',
            'is_pediatric' => 'boolean',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    /**
     * Una habitación está llena cuando todas sus camillas habilitadas
     * (no en mantenimiento o fuera de servicio) están ocupadas.
     */
    public function isFull(): bool
    {
        $enabledBeds = $this->beds()
            ->whereNotIn('status', ['fuera_de_servicio', 'mantenimiento'])
            ->get();

        if ($enabledBeds->isEmpty()) {
            return false;
        }

        return $enabledBeds->every(fn (Bed $bed) => $bed->status === 'ocupada');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'disponible';
    }
}
