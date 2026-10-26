<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Consent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'episode_id',
        'type',
        'granted_by',
        'granted_by_relationship',
        'scope',
        'text_version',
        'captured_by_user_id',
        'granted_at',
        'revoked_at',
        'ip_hash',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'scope' => 'array',
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Registro inmutable: solo se permite registrar la fecha de revocación una única vez
        static::updating(function (Consent $consent) {
            if ($consent->isDirty('revoked_at') && $consent->getOriginal('revoked_at') === null && count($consent->getDirty()) === 1) {
                return;
            }

            throw new RuntimeException('Consent records are immutable; revoke and create a new row instead.');
        });

        static::deleting(function () {
            throw new RuntimeException('Consent records cannot be deleted due to legal compliance.');
        });
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(EmergencyEpisode::class, 'episode_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }
}
