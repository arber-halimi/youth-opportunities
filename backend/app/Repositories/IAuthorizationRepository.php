<?php

namespace App\Repositories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

interface IAuthorizationRepository
{
    public function lockSuperAdminRole(): ?Role;

    public function hasPermission(User $user, string $permission): bool;

    /** @return Collection<string, int> */
    public function roleIds(array $keys): Collection;

    /** @return list<string> */
    public function roleKeys(User $user): array;

    public function syncRoles(User $user, array $roleIds): void;

    public function hasSuperAdmin(Role $role): bool;

    public function hasOtherActiveSuperAdmin(Role $role, User $user): bool;

    public function audit(?User $actor, User $subject, string $action, array $changes): void;
}
