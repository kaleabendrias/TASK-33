<?php

namespace App\Domain\Traits;

use App\Domain\Models\ChangeHistory;

/**
 * Automatically records field-level changes to the append-only change_history table.
 *
 * Models using this trait should define:
 *   protected array $trackedFields = ['field1', 'field2', ...];
 */
trait TracksChanges
{
    public static function bootTracksChanges(): void
    {
        static::updated(function ($model) {
            $tracked = $model->trackedFields ?? [];
            $userId = request()->attributes->get('auth_user')?->id;

            foreach ($model->getDirty() as $field => $newValue) {
                if (!in_array($field, $tracked, true)) {
                    continue;
                }

                ChangeHistory::create([
                    'trackable_type' => $model->getMorphClass(),
                    'trackable_id'   => $model->getKey(),
                    'field_name'     => $field,
                    'old_value'      => $model->getOriginal($field),
                    'new_value'      => $newValue,
                    'changed_by'     => $userId,
                    'created_at'     => now(),
                ]);
            }
        });
    }
}
