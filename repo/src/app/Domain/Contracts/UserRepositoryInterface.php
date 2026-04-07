<?php

namespace App\Domain\Contracts;

use App\Domain\Models\User;

interface UserRepositoryInterface
{
    public function findByUsername(string $username): ?User;
    public function findOrFail(int $id): User;
    public function create(array $data): User;
    public function update(User $user, array $data): User;
}
