<?php

namespace App\Repositories;

use App\Models\User;

interface IUserRepository extends IRepository
{
    public function create(array $attributes): User;

    public function findByEmailForUpdate(string $email): ?User;

    public function markEmailVerified(User $user): ?User;

    public function replacePassword(User $user, string $passwordHash): void;
}
