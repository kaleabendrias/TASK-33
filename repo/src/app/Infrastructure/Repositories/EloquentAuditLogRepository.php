<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Contracts\AuditLogRepositoryInterface;
use App\Domain\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): void {
        $user = request()->attributes->get('auth_user');

        AuditLog::create([
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'actor_id'    => $user?->id,
            'actor_role'  => $user?->role,
            'ip_address'  => request()->ip(),
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'metadata'    => $metadata,
            'created_at'  => now(),
        ]);
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = AuditLog::with('actor')->orderByDesc('created_at');

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['entity_id'])) {
            $query->where('entity_id', $filters['entity_id']);
        }

        if (!empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->paginate($perPage);
    }
}
