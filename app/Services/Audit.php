<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Str;

class Audit
{
    private const FORBIDDEN = ['password', 'password_confirmation', 'code', 'otp', 'token', '_token', 'cookie', 'session'];

    public static function log(string $event, ?object $model = null, array $metadata = [], ?int $userId = null): void
    {
        $request = request();

        AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'event' => $event,
            'model_type' => $model ? class_basename($model) : null,
            'model_id' => $model->id ?? null,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 255, ''),
            'metadata' => self::clean($metadata) ?: null,
            'created_at' => now(),
        ]);
    }

    private static function clean(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::FORBIDDEN, true)) {
                unset($data[$key]);
            }
        }

        return $data;
    }
}
