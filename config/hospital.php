<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pre-registration Expiration TTL
    |--------------------------------------------------------------------------
    |
    | Defines how many hours an unused pre-registration remains active before
    | expiring automatically via scheduled task (default: 24 hours).
    |
    */
    'preregistration_ttl_hours' => (int) env('PREREGISTRATION_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Blind Index Key
    |--------------------------------------------------------------------------
    |
    | Key used to compute HMAC blind indices for sensitive searchable fields
    | such as patient document numbers (separate from APP_KEY).
    |
    */
    'blind_index_key' => env('BLIND_INDEX_KEY', hash('sha256', (string) env('APP_KEY', 'hospital-blind-index-secret'))),

    /*
    |--------------------------------------------------------------------------
    | Ley 1581 Consent Text Version
    |--------------------------------------------------------------------------
    |
    | Version of the data protection notice and informed consent.
    |
    */
    'consent_version' => env('HOSPITAL_CONSENT_VERSION', 'v1.0'),
];
