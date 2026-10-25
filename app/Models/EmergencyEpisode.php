<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmergencyEpisode extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'patient_id',
        'public_code',
        'origin',
        'status',
        'triage_level',
        'triage_user_id',
        'triage_at',
        'reason',
        'arrived_at',
        'admitted_at',
        'discharged_at',
        'discharge_type',
        'closed_at',
        'pre_registration_expires_at',
        'responsible_user_id',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
        'triage_at' => 'datetime',
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
        'closed_at' => 'datetime',
        'pre_registration_expires_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function clinicalData(): HasOne
    {
        return $this->hasOne(EpisodeClinicalData::class, 'episode_id');
    }

    public function companion(): HasOne
    {
        return $this->hasOne(Companion::class, 'episode_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class, 'episode_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function triageUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triage_user_id');
    }

    public function bedAssignments(): HasMany
    {
        return $this->hasMany(BedAssignment::class, 'episode_id');
    }

    public function activeBedAssignment(): HasOne
    {
        return $this->hasOne(BedAssignment::class, 'episode_id')->whereNull('released_at');
    }

    public function clinicalEntries(): HasMany
    {
        return $this->hasMany(ClinicalEntry::class, 'episode_id')->orderBy('performed_at', 'asc');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EpisodeAssignment::class, 'episode_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(EpisodeAssignment::class, 'episode_id')->whereNull('ended_at');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['egresado', 'expirado'], true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['egresado', 'expirado'], true);
    }

    /**
     * Generate high-entropy public code (8 characters without ambiguous 0/O/1/I)
     */
    public static function generatePublicCode(): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $maxIndex = strlen($chars) - 1;

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[random_int(0, $maxIndex)];
            }
        } while (self::where('public_code', $code)->exists());

        return $code;
    }
}
