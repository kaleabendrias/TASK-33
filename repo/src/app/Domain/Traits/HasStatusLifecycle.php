<?php

namespace App\Domain\Traits;

use App\Domain\Models\StatusTransition;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Adds status lifecycle management with transition validation.
 *
 * Models must define:
 *   public static function allowedTransitions(): array
 *   // Returns ['from_status' => ['to_status1', 'to_status2'], ...]
 */
trait HasStatusLifecycle
{
    public function statusTransitions(): MorphMany
    {
        return $this->morphMany(StatusTransition::class, 'transitionable');
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = static::allowedTransitions();
        $current = $this->status ?? null;

        return isset($allowed[$current]) && in_array($newStatus, $allowed[$current], true);
    }

    public function transitionTo(string $newStatus, ?string $reason = null, ?int $userId = null): static
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \DomainException(
                "Cannot transition from '{$this->status}' to '{$newStatus}'."
            );
        }

        $from = $this->status;

        $this->update(['status' => $newStatus]);

        StatusTransition::create([
            'transitionable_type' => $this->getMorphClass(),
            'transitionable_id'   => $this->getKey(),
            'from_status'         => $from,
            'to_status'           => $newStatus,
            'reason'              => $reason,
            'transitioned_by'     => $userId ?? request()->attributes->get('auth_user')?->id,
            'created_at'          => now(),
        ]);

        return $this->refresh();
    }
}
