<?php

namespace App\Services\Auth;

use App\Models\User;

class DevelopmentLogin
{
    public function isEnabled(): bool
    {
        return app()->environment(['local', 'testing'])
            && (bool) config('auth.development_login.enabled');
    }

    public function user(): ?User
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $configuredEmail = trim((string) config('auth.development_login.user_email'));

        if ($configuredEmail !== '') {
            return User::query()->where('email', $configuredEmail)->first();
        }

        $users = User::query()->oldest('id')->limit(2)->get();

        return $users->count() === 1 ? $users->first() : null;
    }

    public function isAvailable(): bool
    {
        return $this->user() instanceof User;
    }
}
