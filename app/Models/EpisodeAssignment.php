<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpisodeAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'user_id',
        'role',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(EmergencyEpisode::class, 'episode_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
