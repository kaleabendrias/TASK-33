<?php

namespace App\Domain\Policies;

use App\Domain\Models\Order;
use App\Domain\Models\Resource;
use App\Domain\Models\Settlement;
use App\Domain\Models\User;

class AttachmentPolicy
{
    /** Maximum file size in bytes (20 MB) */
    public const MAX_SIZE_BYTES = 20 * 1024 * 1024;

    /**
     * Whitelist of model classes that may receive attachment uploads.
     * Maps the short name (case-insensitive) to the canonical FQCN.
     */
    public const ALLOWED_ATTACHABLES = [
        'order'      => Order::class,
        'resource'   => Resource::class,
        'settlement' => Settlement::class,
    ];

    /** Resolve a user-supplied attachable_type to a canonical FQCN, or null if rejected. */
    public static function canonicalAttachableType(string $type): ?string
    {
        $key = strtolower(trim($type));
        // Allow either short name ('order') or FQCN
        if (isset(self::ALLOWED_ATTACHABLES[$key])) {
            return self::ALLOWED_ATTACHABLES[$key];
        }
        foreach (self::ALLOWED_ATTACHABLES as $fqcn) {
            if ($type === $fqcn) return $fqcn;
        }
        return null;
    }

    /**
     * Decide whether $user may attach to the given attachable instance.
     * Admins always allowed; otherwise role-based + ownership rules apply.
     */
    public static function canAttachTo(User $user, object $entity): bool
    {
        if ($user->isAdmin()) return true;

        if ($entity instanceof Order) {
            return $entity->user_id === $user->id || $entity->group_leader_id === $user->id;
        }
        if ($entity instanceof Resource) {
            // Only staff+ with profile may attach to resources
            return $user->isAtLeast('staff');
        }
        if ($entity instanceof Settlement) {
            return false; // admin-only
        }
        return false;
    }

    /** Allowed MIME types */
    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public static function validateSize(int $sizeBytes): bool
    {
        return $sizeBytes > 0 && $sizeBytes <= self::MAX_SIZE_BYTES;
    }

    public static function validateMimeType(string $mime): bool
    {
        return in_array($mime, self::ALLOWED_MIME_TYPES, true);
    }

    public static function validate(int $sizeBytes, string $mime): ?array
    {
        $errors = [];

        if (!self::validateSize($sizeBytes)) {
            $errors[] = 'File size must be between 1 byte and 20 MB.';
        }

        if (!self::validateMimeType($mime)) {
            $errors[] = 'File type not allowed. Accepted: PDF, JPEG, PNG, WebP, CSV, XLSX, DOCX.';
        }

        return $errors ?: null;
    }
}
