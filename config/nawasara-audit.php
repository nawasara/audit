<?php

return [
    'enabled' => env('NAWASARA_AUDIT_ENABLED', true),

    'ignored_attributes' => [
        'password',
        'remember_token',
        'updated_at',
    ],

    'retention_days' => env('NAWASARA_AUDIT_RETENTION_DAYS', 90),
];
