<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\IUserRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Throwable;

class PasswordResetService
{
    public function __construct(private readonly IUserRepository $users) {}

    public function sendLink(string $email): void
    {
        DB::transaction(function () use ($email): void {
            // Serialize token issuance with reset for the same account.
            $this->users->findByEmailForUpdate($email);

            Password::broker('users')->sendResetLink(['email' => $email], function (User $user, string $token): void {
                DB::afterCommit(function () use ($user, $token): void {
                    try {
                        $user->sendPasswordResetNotification($token);
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                });
            });
        });
    }

    public function reset(#[\SensitiveParameter] array $credentials): bool
    {
        return DB::transaction(function () use ($credentials): bool {
            $this->users->findByEmailForUpdate($credentials['email']);

            $status = Password::broker('users')->reset($credentials, function (User $user, string $password): void {
                $this->users->replacePassword($user, Hash::make($password));
                DB::afterCommit(fn () => event(new PasswordReset($user)));
            });

            return $status === Password::PASSWORD_RESET;
        });
    }
}
