<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRolesRequest;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\UserAuthorizationService;
use Illuminate\Http\Response;

class AdminUserController extends ApiController
{
    public function __construct(private readonly UserAuthorizationService $authorization) {}

    public function updateRoles(UpdateUserRolesRequest $request, User $user): Response
    {
        $this->authorization->updateRoles($request->user(), $user, $request->validated('roles'));

        return $this->noContent();
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): Response
    {
        $this->authorization->updateStatus($request->user(), $user, $request->validated('status'));

        return $this->noContent();
    }
}
