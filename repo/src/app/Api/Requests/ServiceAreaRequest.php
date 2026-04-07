<?php

namespace App\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('service_area');

        return [
            'name' => $this->isMethod('POST') ? 'required|string|max:255' : 'sometimes|string|max:255',
            'slug' => "sometimes|string|max:255|unique:service_areas,slug,{$id}",
            'description' => 'sometimes|nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
