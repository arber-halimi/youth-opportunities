<?php

namespace App\Console\Commands;

use App\Services\UserAuthorizationService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class AssignFirstSuperAdmin extends Command
{
    protected $signature = 'auth:assign-first-super-admin {email : Email of an existing user}';

    protected $description = 'Assign the Super Admin role to the first account';

    public function handle(UserAuthorizationService $authorization): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        try {
            $assigned = $authorization->assignFirstSuperAdmin($email);
        } catch (ValidationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($assigned ? 'Super Admin role assigned.' : 'This account already has the Super Admin role.');

        return self::SUCCESS;
    }
}
