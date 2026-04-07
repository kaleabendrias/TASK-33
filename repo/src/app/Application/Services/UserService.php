<?php

namespace App\Application\Services;

use App\Domain\Contracts\AuditLogRepositoryInterface;
use App\Domain\Contracts\UserRepositoryInterface;
use App\Domain\Models\User;
use App\Domain\Policies\PasswordPolicy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogRepositoryInterface $audit,
    ) {}

    public function list(): Collection
    {
        return User::orderBy('username')->get();
    }

    public function get(int $id): User
    {
        return $this->users->findOrFail($id);
    }

    public function create(array $data): User
    {
        $errors = PasswordPolicy::validate($data['password']);
        if ($errors) {
            throw ValidationException::withMessages(['password' => $errors]);
        }

        $user = $this->users->create($data);

        $this->audit->log('user_created', 'User', $user->id, null, [
            'username' => $user->username,
            'role'     => $user->role,
        ]);

        return $user;
    }

    public function update(int $id, array $data): User
    {
        if (isset($data['password'])) {
            $errors = PasswordPolicy::validate($data['password']);
            if ($errors) {
                throw ValidationException::withMessages(['password' => $errors]);
            }
            $data['password_changed_at'] = now();
        }

        $user = $this->users->findOrFail($id);
        $old = $user->only(['username', 'role', 'is_active']);

        $user = $this->users->update($user, $data);

        $this->audit->log('user_updated', 'User', $user->id, $old, [
            'username' => $user->username,
            'role'     => $user->role,
        ]);

        return $user;
    }
}
