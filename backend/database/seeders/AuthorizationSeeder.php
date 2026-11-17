<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        $adminPermissions = [
            'opportunities.moderate',
            'reports.view',
        ];

        $permissionsByRole = [
            'user' => [],
            'admin' => $adminPermissions,
            'super_admin' => [
                ...$adminPermissions,
                'users.roles.manage',
                'users.status.manage',
                'organizations.approve',
            ],
        ];

        DB::transaction(function () use ($permissionsByRole): void {
            $now = now();
            $roleKeys = array_keys($permissionsByRole);
            $permissionKeys = $permissionsByRole['super_admin'];

            foreach (['roles' => $roleKeys, 'permissions' => $permissionKeys] as $table => $keys) {
                $rows = array_map(
                    fn(string $key) => [
                        'key' => $key,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $keys,
                );

                DB::table($table)->upsert($rows, ['key'], ['key']);
            }

            $roleIds = DB::table('roles')
                ->whereIn('key', $roleKeys)
                ->pluck('id', 'key');

            $permissionIds = DB::table('permissions')
                ->whereIn('key', $permissionKeys)
                ->pluck('id', 'key');

            $permissionLinks = [];

            foreach ($permissionsByRole as $roleKey => $permissions) {
                foreach ($permissions as $permissionKey) {
                    $permissionLinks[] = [
                        'permission_id' => $permissionIds[$permissionKey],
                        'role_id' => $roleIds[$roleKey],
                    ];
                }
            }

            DB::table('permission_role')->upsert(
                $permissionLinks,
                ['permission_id', 'role_id'],
                ['role_id'],
            );

            $baseRoleId = $roleIds['user'];

            $usersWithoutRoles = DB::table('users')
                ->select('users.id')
                ->selectRaw('? as role_id', [$baseRoleId])
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('role_user')
                        ->whereColumn('role_user.user_id', 'users.id');
                });

            DB::table('role_user')->insertUsing(
                ['user_id', 'role_id'],
                $usersWithoutRoles,
            );
        });
    }
}
