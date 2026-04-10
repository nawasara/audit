<?php

namespace Nawasara\Audit\Listeners;

use Illuminate\Auth\Events\Failed;
use Nawasara\Audit\Models\LoginAttempt;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        LoginAttempt::create([
            'user_id' => $event->user?->id,
            'username_attempted' => $event->credentials['email'] ?? $event->credentials['username'] ?? 'unknown',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'method' => 'local',
            'status' => 'failed',
            'failure_reason' => 'Invalid credentials',
        ]);
    }
}
