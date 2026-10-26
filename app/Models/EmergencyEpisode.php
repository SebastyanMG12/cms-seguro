<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'nn_code',
        'is_nn',
        'apparent_sex',
        'estimated_age',
        'distinguishing_marks',
        'arrival_mode',
        'brought_by_name',
        'brought_by_contact',
        'no_consent_reason',
        'original_nn_patient_id',
        'reconciled_at',
        'reconciled_by_user_id',
        'reconciliation_evidence',
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
        'is_nn' => 'boolean',
        'arrived_at' => 'datetime',
        'triage_at' => 'datetime',
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
        'closed_at' => 'datetime',
        'reconciled_at' => 'datetime',
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

    public function supportUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'episode_assignments', 'episode_id', 'user_id')
            ->wherePivot('role', 'apoyo')
            ->wherePivotNull('ended_at');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['egresado', 'expirado'], true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['egresado', 'expirado'], true);
    }

    public function originalNnPatient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'original_nn_patient_id');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    public function isNn(): bool
    {
        return (bool) $this->is_nn;
    }

    public function isReconciled(): bool
    {
        return (bool) ($this->is_nn && ! is_null($this->reconciled_at));
    }

    public function isAssignedUser(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->responsible_user_id === $user->id) {
            return true;
        }

        return $this->activeAssignments()->where('user_id', $user->id)->exists();
    }

    /**
     * Retorna el nombre enmascarado del paciente (Primer nombre e inicial de apellido) para consultas de acompañante.
     */
    public function getMaskedPatientNameAttribute(): string
    {
        if ($this->is_nn) {
            return $this->nn_code ?? 'Paciente sin identificar';
        }

        $fullName = trim($this->patient?->full_name ?? '');
        if ($fullName === '') {
            return 'Paciente';
        }

        $parts = preg_split('/\s+/', $fullName);
        if (count($parts) === 1) {
            return $parts[0];
        }

        $firstName = $parts[0];
        $lastInitial = mb_strtoupper(mb_substr($parts[1], 0, 1));

        return "{$firstName} {$lastInitial}.";
    }

    /**
     * Verifica si existe consentimiento activo otorgado para compartir información clínica con acompañante.
     */
    public function hasActiveCompanionConsent(): bool
    {
        $latestConsent = $this->consents()
            ->where('type', 'compartir_acompanante')
            ->latest('id')
            ->first();

        return $latestConsent !== null && $latestConsent->revoked_at === null;
    }

    /**
     * Retorna el registro de consentimiento activo si existe.
     */
    public function activeCompanionConsent(): ?Consent
    {
        return $this->consents()
            ->where('type', 'compartir_acompanante')
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();
    }

    /**
     * Determina si el acceso del acompañante ha expirado (24 horas después del egreso).
     */
    public function isCompanionAccessExpired(): bool
    {
        if ($this->isOpen()) {
            return false;
        }

        $closedTime = $this->closed_at ?? $this->discharged_at;
        if (! $closedTime) {
            return false;
        }

        return $closedTime->copy()->addHours(24)->isPast();
    }

    /**
     * Retorna el PIN predeterminado para el acompañante (últimos 4 dígitos del documento o del código NN).
     */
    public function getDefaultCompanionPin(): string
    {
        if ($this->is_nn) {
            if ($this->nn_code && preg_match('/(\d{4})$/', $this->nn_code, $matches)) {
                return $matches[1];
            }

            return '0000';
        }

        $doc = preg_replace('/\D/', '', (string) ($this->patient?->document_number ?? ''));
        if (strlen($doc) >= 4) {
            return substr($doc, -4);
        }

        if (strlen($doc) > 0) {
            return str_pad($doc, 4, '0', STR_PAD_LEFT);
        }

        return substr(preg_replace('/[^a-zA-Z0-9]/', '', $this->public_code), -4);
    }

    /**
     * Retorna el PIN activo actual (el personalizado si existe, o el predeterminado).
     */
    public function getCompanionPin(): string
    {
        if ($this->companion && ! empty($this->companion->pin)) {
            return (string) $this->companion->pin;
        }

        return $this->getDefaultCompanionPin();
    }

    /**
     * Retorna una descripción clara de la fuente del PIN para mostrar al médico o administrador.
     */
    public function getCompanionPinSourceDescription(): string
    {
        if ($this->companion && ! empty($this->companion->pin) && $this->companion->pin !== $this->getDefaultCompanionPin()) {
            return 'PIN asignado en hospital';
        }

        if ($this->is_nn) {
            return "Últimos 4 dígitos del identificador NN ({$this->nn_code})";
        }

        if (! empty($this->patient?->document_number)) {
            return "Últimos 4 dígitos del documento ({$this->patient->document_number})";
        }

        return 'PIN predeterminado del episodio';
    }

    /**
     * Generate sequential NN identifier (format: NN-AAAAMMDD-XXXX)
     */
    public static function generateNnCode(?\DateTimeInterface $date = null): string
    {
        $dateFormatted = ($date ?? now())->format('Ymd');
        $prefix = "NN-{$dateFormatted}-";

        $countToday = self::where('nn_code', 'like', "{$prefix}%")->count();
        $counter = $countToday + 1;
        $code = sprintf('%s%04d', $prefix, $counter);

        while (self::where('nn_code', $code)->exists()) {
            $counter++;
            $code = sprintf('%s%04d', $prefix, $counter);
        }

        return $code;
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
