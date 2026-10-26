<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffProfile extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVO = 'activo';

    public const STATUS_INACTIVO = 'inactivo';

    public const STATUS_EN_DESCANSO = 'en_descanso';

    protected $fillable = [
        'user_id',
        'code',
        'specialty',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * El personal en descanso, inactivo o eliminado no es asignable.
     */
    public function isAssignable(): bool
    {
        return ! $this->trashed() && $this->status === self::STATUS_ACTIVO && $this->user?->isActive();
    }
}
