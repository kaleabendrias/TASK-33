<?php

namespace App\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'service_area' => new ServiceAreaResource($this->whenLoaded('serviceArea')),
            'role' => new RoleResource($this->whenLoaded('role')),
            'capacity_hours' => (float) $this->capacity_hours,
            'is_available' => $this->is_available,
            'status' => $this->status,
            'children_count' => $this->whenCounted('children'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
