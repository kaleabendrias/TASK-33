<?php

namespace App\Domain\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AuditLogRepositoryInterface
{
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): void;

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;
}
