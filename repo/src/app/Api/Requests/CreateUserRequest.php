<?php

namespace App\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // RBAC enforced at route middleware level
    }

    public function rules(): array
    {
        return [
            'username'  => 'required|string|max:100|unique:users,username',
            'password'  => 'required|string|min:12',
            'full_name' => 'required|string|max:255',
            'email'     => 'sometimes|nullable|email|max:255',
            'phone'     => 'sometimes|nullable|string|max:30',
            'role'      => 'sometimes|in:user,staff,group-leader,admin',
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated($key, $default);

        if ($key !== null) {
            return $data;
        }

        // Map plaintext email/phone to encrypted field names
        if (isset($data['email'])) {
            $data['email_encrypted'] = $data['email'];
            unset($data['email']);
        }
        if (isset($data['phone'])) {
            $data['phone_encrypted'] = $data['phone'];
            unset($data['phone']);
        }

        return $data;
    }
}
