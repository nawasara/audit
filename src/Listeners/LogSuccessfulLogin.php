<?php

namespace Nawasara\Audit\Listeners;

use Illuminate\Auth\Events\Login;
use Nawasara\Audit\Models\LoginAttempt;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        LoginAttempt::create([
            'user_id' => $event->user->id,
            'username_attempted' => $event->user->email ?? $event->user->username,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'method' => $event->user->auth_type === 'sso' ? 'sso' : 'local',
            'status' => 'success',
        ]);
    }
}
