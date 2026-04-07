<?php

namespace App\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Canonical lifecycle states. Mirrors Resource::allowedTransitions() keys. */
    public const ALLOWED_STATUSES = ['available', 'reserved', 'in_use', 'maintenance', 'decommissioned'];

    public function rules(): array
    {
        return [
            'parent_id' => 'sometimes|nullable|exists:resources,id',
            'name' => $this->isMethod('POST') ? 'required|string|max:255' : 'sometimes|string|max:255',
            'service_area_id' => $this->isMethod('POST') ? 'required|exists:service_areas,id' : 'sometimes|exists:service_areas,id',
            'role_id' => $this->isMethod('POST') ? 'required|exists:roles,id' : 'sometimes|exists:roles,id',
            'capacity_hours' => 'sometimes|numeric|min:0|max:2080',
            'is_available' => 'sometimes|boolean',
            // Optional on create (Resource model defaults to 'available'),
            // optional on update (no-op if absent), but if provided must be
            // one of the canonical lifecycle states. This blocks the legacy
            // 'draft' value from ever entering through the API again.
            'status' => 'sometimes|string|in:' . implode(',', self::ALLOWED_STATUSES),
        ];
    }
}
