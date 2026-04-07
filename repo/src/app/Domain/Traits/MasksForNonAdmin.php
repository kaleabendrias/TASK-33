<?php

namespace App\Domain\Traits;

/**
 * Provides field masking for API responses when the current user is not an admin.
 * Used in API Resource classes to hide sensitive data from non-privileged users.
 */
trait MasksForNonAdmin
{
    protected function mask(string $value, int $visibleChars = 3): string
    {
        $len = mb_strlen($value);
        if ($len <= $visibleChars) {
            return str_repeat('*', $len);
        }
        return mb_substr($value, 0, $visibleChars) . str_repeat('*', $len - $visibleChars);
    }

    protected function maskEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $this->mask($email);
        }

        return $this->mask($parts[0], 2) . '@' . $parts[1];
    }

    protected function maskPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $len = mb_strlen($phone);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 4) . mb_substr($phone, -4);
    }

    protected function isCurrentUserAdmin(): bool
    {
        $user = request()->attributes->get('auth_user');
        return $user && $user->role === 'admin';
    }
}
