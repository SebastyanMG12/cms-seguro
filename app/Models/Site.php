<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'status',
    ];

    public function floors(): HasMany
    {
        return $this->hasMany(Floor::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'activo';
    }
}
