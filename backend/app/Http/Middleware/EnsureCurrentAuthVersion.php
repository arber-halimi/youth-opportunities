<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;

class EnsureCurrentAuthVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = auth('api');
        $user = $guard->user();

        try {
            $version = $guard->payload()->get('auth_version');
        } catch (JWTException) {
            throw new AuthenticationException;
        }

        if ($version !== $user->auth_version) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
