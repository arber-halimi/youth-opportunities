<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Repositories\IAuthorizationRepository;
use App\Repositories\IUserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class UserAuthorizationService
{
    public function __construct(
        private readonly IAuthorizationRepository $authorization,
        private readonly IUserRepository $users,
    ) {}

    public function updateRoles(User $actor, User $subject, array $roles): void
    {
        DB::transaction(function () use ($actor, $subject, $roles): void {
            $superAdmin = $this->lockSuperAdmin();
            $subject = $this->users->findForUpdate($subject->getKey());
            Gate::forUser($actor)->authorize('updateRoles', $subject);

            $roleIds = $this->authorization->roleIds($roles);
            if (array_diff($roles, $roleIds->keys()->all()) !== []) {
                throw ValidationException::withMessages(['roles' => 'One or more roles do not exist.']);
            }

            $before = $this->authorization->roleKeys($subject);
            sort($roles, SORT_STRING);

            if ($before === $roles) {
                return;
            }

            if ($subject->suspended_at === null && in_array('super_admin', $before, true)
                && ! in_array('super_admin', $roles, true)) {
                $this->ensureAnotherActiveSuperAdmin($superAdmin, $subject, 'roles');
            }

            $this->authorization->syncRoles($subject, $roleIds->values()->all());
            $this->authorization->audit($actor, $subject, 'users.roles.updated', [
                'roles' => ['before' => $before, 'after' => $roles],
            ]);
        }, 3);
    }

    public function updateStatus(User $actor, User $subject, string $status): void
    {
        DB::transaction(function () use ($actor, $subject, $status): void {
            $superAdmin = $this->lockSuperAdmin();
            $subject = $this->users->findForUpdate($subject->getKey());
            Gate::forUser($actor)->authorize('updateStatus', $subject);

            $before = $subject->suspended_at === null ? 'active' : 'suspended';
            if ($before === $status) {
                return;
            }

            $suspended = $status === 'suspended';
            if ($suspended && in_array('super_admin', $this->authorization->roleKeys($subject), true)) {
                $this->ensureAnotherActiveSuperAdmin($superAdmin, $subject, 'status');
            }

            $this->users->setSuspended($subject, $suspended);
            $this->authorization->audit($actor, $subject, 'users.status.updated', [
                'status' => ['before' => $before, 'after' => $status],
            ]);
        }, 3);
    }

    public function assignFirstSuperAdmin(string $email): bool
    {
        return DB::transaction(function () use ($email): bool {
            $role = $this->authorization->lockSuperAdminRole();
            if ($role === null) {
                throw ValidationException::withMessages([
                    'role' => 'Super Admin role is missing. Run php artisan db:seed --class=AuthorizationSeeder first.',
                ]);
            }

            $user = $this->users->findByEmailForUpdate($email);
            if ($user === null || $user->suspended_at !== null) {
                throw ValidationException::withMessages(['email' => 'An active account with that email is required.']);
            }

            $before = $this->authorization->roleKeys($user);
            if (in_array('super_admin', $before, true)) {
                return false;
            }

            if ($this->authorization->hasSuperAdmin($role)) {
                throw ValidationException::withMessages(['role' => 'The Super Admin role is already assigned to another account.']);
            }

            $this->users->assignRoleByKey($user, 'super_admin');
            $this->authorization->audit(null, $user, 'users.roles.updated', [
                'roles' => ['before' => $before, 'after' => $this->authorization->roleKeys($user)],
            ]);

            return true;
        }, 3);
    }

    private function lockSuperAdmin(): Role
    {
        // Serialize role and status changes before checking the active Super Admin count.
        return $this->authorization->lockSuperAdminRole()
            ?? throw new LogicException('Authorization roles have not been seeded.');
    }

    private function ensureAnotherActiveSuperAdmin(Role $role, User $subject, string $field): void
    {
        if (! $this->authorization->hasOtherActiveSuperAdmin($role, $subject)) {
            throw ValidationException::withMessages([$field => 'At least one Super Admin must remain active.']);
        }
    }
}
