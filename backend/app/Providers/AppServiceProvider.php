<?php

namespace App\Providers;

use App\Http\Parsers\BearerTokenParser;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function (): void {
            $this->app['tymon.jwt.parser']->setChain([new BearerTokenParser]);
        });

        VerifyEmail::createUrlUsing(function (User $user): string {
            $path = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
                absolute: false,
            );

            return $this->frontendLink('/verify-email', [
                'verification_url' => rtrim(config('app.url'), '/').$path,
            ]);
        });

        ResetPassword::createUrlUsing(fn (User $user, string $token): string => $this->frontendLink(
            '/reset-password',
            ['email' => $user->getEmailForPasswordReset(), 'token' => $token],
        ));
    }

    private function frontendLink(string $path, array $parameters): string
    {
        return rtrim(config('auth.frontend_url'), '/').$path.'?'
            .http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
