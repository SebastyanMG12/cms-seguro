<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shift extends Model
{
    use HasFactory;

    public const STATUS_PROGRAMADO = 'programado';

    public const STATUS_ACTIVO = 'activo';

    public const STATUS_FINALIZADO = 'finalizado';

    public const STATUS_CANCELADO = 'cancelado';

    protected $fillable = [
        'user_id',
        'starts_at',
        'ends_at',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVO;
    }
}
