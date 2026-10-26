<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class ClinicalEntry extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'episode_id',
        'type',
        'content',
        'author_user_id',
        'performed_at',
        'references_entry_id',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'content' => 'encrypted',
        'performed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Resolución 1995 de 1999: La historia clínica es inalterable.
        static::updating(function () {
            throw new RuntimeException('Clinical entries are immutable and cannot be updated. Use a clarification or annulment entry instead.');
        });

        static::deleting(function () {
            throw new RuntimeException('Clinical entries cannot be deleted due to medical records retention regulations.');
        });
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(EmergencyEpisode::class, 'episode_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function referencedEntry(): BelongsTo
    {
        return $this->belongsTo(ClinicalEntry::class, 'references_entry_id');
    }

    public function clarifications(): HasMany
    {
        return $this->hasMany(ClinicalEntry::class, 'references_entry_id')->where('type', 'aclaracion');
    }

    public function annulments(): HasMany
    {
        return $this->hasMany(ClinicalEntry::class, 'references_entry_id')->where('type', 'anulacion');
    }

    public function isAnnulled(): bool
    {
        return $this->annulments()->exists();
    }
}
