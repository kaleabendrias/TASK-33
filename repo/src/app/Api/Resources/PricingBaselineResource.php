<?php

namespace App\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingBaselineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_area' => new ServiceAreaResource($this->whenLoaded('serviceArea')),
            'role' => new RoleResource($this->whenLoaded('role')),
            'hourly_rate' => (float) $this->hourly_rate,
            'currency' => $this->currency,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_until' => $this->effective_until?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
