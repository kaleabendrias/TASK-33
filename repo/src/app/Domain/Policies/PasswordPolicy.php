<?php

namespace App\Domain\Policies;

class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    /**
     * Validate password complexity:
     *  - minimum 12 characters
     *  - at least one uppercase letter
     *  - at least one lowercase letter
     *  - at least one digit
     *  - at least one special character
     *
     * Returns null on success, or an array of violation messages.
     */
    public static function validate(string $password): ?array
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . self::MIN_LENGTH . ' characters.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one digit.';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        return $errors ?: null;
    }
}
