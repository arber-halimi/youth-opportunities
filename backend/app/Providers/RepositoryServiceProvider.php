<?php

namespace App\Providers;

use App\Repositories\Eloquent\AuthorizationRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\IAuthorizationRepository;
use App\Repositories\IUserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IUserRepository::class, UserRepository::class);
        $this->app->bind(IAuthorizationRepository::class, AuthorizationRepository::class);
    }
}
