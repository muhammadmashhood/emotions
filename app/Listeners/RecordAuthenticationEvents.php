<?php

namespace App\Listeners;

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

class RecordAuthenticationEvents
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $this->audit->log('auth.login', $user, [], $user->hospital_id, $user);
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;
        $user = $event->user instanceof User ? $event->user : null;

        $this->audit->log('auth.login_failed', $user, ['email' => is_string($email) ? $email : null], $user?->hospital_id, null);
    }
}
