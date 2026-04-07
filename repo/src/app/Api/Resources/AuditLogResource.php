<?php

namespace App\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'action'      => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id'   => $this->entity_id,
            'actor_id'    => $this->actor_id,
            'actor_role'  => $this->actor_role,
            'ip_address'  => $this->ip_address,
            'old_values'  => $this->old_values,
            'new_values'  => $this->new_values,
            'metadata'    => $this->metadata,
            'created_at'  => $this->created_at?->toIso8601String(),
        ];
    }
}
