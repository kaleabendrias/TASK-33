<?php

namespace App\Api\Resources;

use App\Domain\Traits\MasksForNonAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    use MasksForNonAdmin;

    public function toArray(Request $request): array
    {
        $isAdmin = $this->isCurrentUserAdmin();

        $email = $this->resource->decryptField('email_encrypted');
        $phone = $this->resource->decryptField('phone_encrypted');

        return [
            'id'        => $this->id,
            'username'  => $this->username,
            'full_name' => $this->full_name,
            'email'     => $isAdmin ? $email : $this->maskEmail($email),
            'phone'     => $isAdmin ? $phone : $this->maskPhone($phone),
            'role'      => $this->role,
            'is_active' => $this->is_active,
            'password_changed_at' => $this->password_changed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
