<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\IUserRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Tymon\JWTAuth\JWTGuard;

class AuthService
{
    public function __construct(private readonly IUserRepository $users) {}

    public function login(string $email, #[\SensitiveParameter] string $password): ?array
    {
        /** @var JWTGuard $guard */
        $guard = auth('api');
        $token = $guard->attempt(['email' => $email, 'password' => $password]);

        if ($token === false) {
            return null;
        }

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) $guard->factory()->getTTL() * 60,
        ];
    }

    public function logout(): void
    {
        auth('api')->logout();
    }

    public function register(#[\SensitiveParameter] array $attributes): User
    {
        try {
            $user = $this->users->create($attributes);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => 'email']),
            ]);
        }

        try {
            event(new Registered($user));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
        }

        return $user;
    }

    public function verifyEmail(User $user): void
    {
        $verifiedUser = $this->users->markEmailVerified($user);

        if ($verifiedUser !== null) {
            event(new Verified($verifiedUser));
        }
    }

    public function resendVerification(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }
}
