<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpisodeClinicalData extends Model
{
    use HasFactory;

    protected $table = 'episode_clinical_data';

    protected $fillable = [
        'episode_id',
        'allergies_type',
        'allergies_detail',
        'blood_type',
        'notes',
        'current_medication',
        'warning_signs',
    ];

    protected $casts = [
        'allergies_detail' => 'encrypted',
        'current_medication' => 'encrypted',
        'warning_signs' => 'array',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(EmergencyEpisode::class, 'episode_id');
    }
}
