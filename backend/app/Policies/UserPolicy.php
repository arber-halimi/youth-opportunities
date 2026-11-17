<?php

namespace App\Policies;

use App\Models\User;
use App\Repositories\IAuthorizationRepository;

class UserPolicy
{
    public function __construct(private readonly IAuthorizationRepository $authorization) {}

    public function updateRoles(User $actor, User $subject): bool
    {
        return $this->authorization->hasPermission($actor, 'users.roles.manage');
    }

    public function updateStatus(User $actor, User $subject): bool
    {
        return $this->authorization->hasPermission($actor, 'users.status.manage');
    }
}
