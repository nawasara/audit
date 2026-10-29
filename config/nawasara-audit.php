<?php

return [
    'enabled' => env('NAWASARA_AUDIT_ENABLED', true),

    'ignored_attributes' => [
        'password',
        'remember_token',
        'updated_at',
    ],

    // How long activity_log and login attempts are kept. At least 30; a
    // smaller or malformed value makes the prune refuse to run rather than
    // delete (see Support\RetentionPolicy).
    'retention_days' => env('NAWASARA_AUDIT_RETENTION_DAYS', 90),

    // Nightly prune of rows older than retention_days. OFF by default:
    // activity_log is shared by every package, and the first run on an
    // environment that has never been pruned permanently deletes all older
    // history at once. Run `php artisan nawasara-audit:prune --dry-run`
    // first, then turn this on deliberately.
    'prune' => [
        'enabled' => (bool) env('NAWASARA_AUDIT_PRUNE_ENABLED', false),
        'at' => env('NAWASARA_AUDIT_PRUNE_AT', '02:30'),
    ],
];
