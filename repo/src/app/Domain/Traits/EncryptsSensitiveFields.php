<?php

namespace App\Domain\Traits;

use Illuminate\Support\Facades\Crypt;

/**
 * Transparently encrypts/decrypts designated model fields using AES-256-CBC,
 * and maintains SHA-256 hash columns for equality lookups without decryption.
 *
 * Models using this trait must define:
 *   protected array $encryptedFields = ['field_name', ...];
 *   protected array $hashIndexFields = ['field_name' => 'hash_column', ...]; // optional
 */
trait EncryptsSensitiveFields
{
    public static function bootEncryptsSensitiveFields(): void
    {
        static::saving(function ($model) {
            foreach ($model->encryptedFields ?? [] as $field) {
                if ($model->isDirty($field) && $model->$field !== null) {
                    $plain = $model->$field;

                    // Store SHA-256 hash for indexed lookups
                    if (isset($model->hashIndexFields[$field])) {
                        $hashCol = $model->hashIndexFields[$field];
                        $model->$hashCol = hash('sha256', mb_strtolower(trim($plain)));
                    }

                    $model->attributes[$field] = Crypt::encryptString($plain);
                }
            }
        });
    }

    /**
     * Decrypt a sensitive field value.
     */
    public function decryptField(string $field): ?string
    {
        $value = $this->attributes[$field] ?? null;
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Find a record by the hash of an encrypted field.
     */
    public static function findByEncryptedField(string $field, string $plainValue): ?static
    {
        $instance = new static();
        $hashCol = $instance->hashIndexFields[$field] ?? null;

        if (!$hashCol) {
            return null;
        }

        $hash = hash('sha256', mb_strtolower(trim($plainValue)));
        return static::where($hashCol, $hash)->first();
    }
}
