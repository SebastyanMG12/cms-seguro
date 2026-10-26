<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Companion extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'name',
        'phone',
        'relationship',
        'pin',
        'pin_hash',
        'verified_by_user_id',
        'verified_at',
        'revoked_at',
    ];

    protected $casts = [
        'phone' => 'encrypted',
        'pin' => 'encrypted',
        'verified_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(EmergencyEpisode::class, 'episode_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
