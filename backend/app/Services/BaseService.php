<?php

namespace App\Services;

use App\Repositories\IRepository;

abstract class BaseService
{
    public function __construct(protected IRepository $repository)
    {
    }
}
