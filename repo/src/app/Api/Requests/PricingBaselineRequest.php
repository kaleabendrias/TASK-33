<?php

namespace App\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PricingBaselineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_area_id' => $this->isMethod('POST') ? 'required|exists:service_areas,id' : 'sometimes|exists:service_areas,id',
            'role_id' => $this->isMethod('POST') ? 'required|exists:roles,id' : 'sometimes|exists:roles,id',
            'hourly_rate' => $this->isMethod('POST') ? 'required|numeric|min:10' : 'sometimes|numeric|min:10',
            'currency' => 'sometimes|string|size:3',
            'effective_from' => $this->isMethod('POST') ? 'required|date' : 'sometimes|date',
            'effective_until' => 'sometimes|nullable|date|after_or_equal:effective_from',
        ];
    }
}
