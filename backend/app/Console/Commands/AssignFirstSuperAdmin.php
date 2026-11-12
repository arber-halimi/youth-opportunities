<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignFirstSuperAdmin extends Command
{
    protected $signature = 'auth:assign-first-super-admin {email : Email of an existing user}';

    protected $description = 'Assign the Super Admin role to the first account';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        return DB::transaction(function () use ($email): int {
            $role = DB::table('roles')->where('key', 'super_admin')->lockForUpdate()->first();

            if ($role === null) {
                $this->error('Super Admin role is missing. Run php artisan db:seed --class=AuthorizationSeeder first.');

                return self::FAILURE;
            }

            $user = DB::table('users')->where('email', $email)->lockForUpdate()->first();

            if ($user === null || $user->suspended_at !== null) {
                $this->error('An active account with that email is required.');

                return self::FAILURE;
            }

            $assignments = DB::table('role_user')->where('role_id', $role->id);

            if ((clone $assignments)->where('user_id', $user->id)->exists()) {
                $this->info('This account already has the Super Admin role.');

                return self::SUCCESS;
            }

            if ($assignments->exists()) {
                $this->error('The Super Admin role is already assigned to another account.');

                return self::FAILURE;
            }

            DB::table('role_user')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);

            $this->info('Super Admin role assigned.');

            return self::SUCCESS;
        });
    }
}
