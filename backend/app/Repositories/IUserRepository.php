<?php

namespace App\Repositories;

use App\Models\User;

interface IUserRepository extends IRepository
{
    public function create(array $attributes): User;

    public function findByEmailForUpdate(string $email): ?User;

    public function markEmailVerified(User $user): ?User;

    public function replacePassword(User $user, string $passwordHash): void;

    public function assignRoleByKey(User $user, string $key): void;

    public function findForUpdate(int $id): User;

    public function setSuspended(User $user, bool $suspended): void;
}
