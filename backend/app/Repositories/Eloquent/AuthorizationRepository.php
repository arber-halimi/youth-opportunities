<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Models\User;
use App\Repositories\IAuthorizationRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuthorizationRepository implements IAuthorizationRepository
{
    public function __construct(private readonly Role $roles) {}

    public function lockSuperAdminRole(): ?Role
    {
        return $this->roles->newQuery()->where('key', 'super_admin')->lockForUpdate()->first();
    }

    public function hasPermission(User $user, string $permission): bool
    {
        return $user->newQuery()
            ->whereKey($user->getKey())
            ->whereNull('suspended_at')
            ->whereHas('roles.permissions', fn ($query) => $query->where('permissions.key', $permission))
            ->exists();
    }

    public function roleIds(array $keys): Collection
    {
        return $this->roles->newQuery()->whereIn('key', $keys)->pluck('id', 'key');
    }

    public function roleKeys(User $user): array
    {
        return $user->roles()->orderBy('roles.key')->pluck('roles.key')->all();
    }

    public function syncRoles(User $user, array $roleIds): void
    {
        $user->roles()->sync($roleIds);
    }

    public function hasSuperAdmin(Role $role): bool
    {
        return $role->users()->exists();
    }

    public function hasOtherActiveSuperAdmin(Role $role, User $user): bool
    {
        return $role->users()->whereKeyNot($user->getKey())->whereNull('users.suspended_at')->exists();
    }

    public function audit(?User $actor, User $subject, string $action, array $changes): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor?->getKey(),
            'subject_type' => 'user',
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'changes' => json_encode($changes, JSON_THROW_ON_ERROR),
        ]);
    }
}
