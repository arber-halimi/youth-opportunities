<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\IUserRepository;

class UserRepository extends BaseRepository implements IUserRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function create(array $attributes): User
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function findByEmailForUpdate(string $email): ?User
    {
        return $this->model->newQuery()->where('email', $email)->lockForUpdate()->first();
    }

    public function markEmailVerified(User $user): ?User
    {
        $updated = $this->model->newQuery()
            ->whereKey($user->getKey())
            ->where('email', $user->email)
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);

        return $updated === 1 ? $user->refresh() : null;
    }

    public function replacePassword(User $user, string $passwordHash): void
    {
        $user->increment('auth_version', 1, ['password' => $passwordHash]);
    }
}
