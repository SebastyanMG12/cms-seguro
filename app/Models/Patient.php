<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'full_name',
        'document_type',
        'document_number',
        'document_index',
        'birth_date',
        'gender',
        'phone',
        'identity_status',
    ];

    protected $casts = [
        'full_name' => 'encrypted',
        'document_number' => 'encrypted',
        'phone' => 'encrypted',
        'birth_date' => 'date',
    ];

    public function episodes(): HasMany
    {
        return $this->hasMany(EmergencyEpisode::class, 'patient_id');
    }

    public static function computeDocumentIndex(?string $type, ?string $number): ?string
    {
        if (empty($type) || empty($number)) {
            return null;
        }

        $normalized = mb_strtoupper(trim($type)).':'.trim($number);
        $key = config('hospital.blind_index_key', 'hospital-blind-index-secret');

        return hash_hmac('sha256', $normalized, $key);
    }
}
