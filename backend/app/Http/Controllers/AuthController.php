<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->created(new UserResource($this->auth->register($request->validated())));
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $result = $this->auth->login($credentials['email'], $credentials['password']);

        if ($result === null) {
            return response()->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json(['message' => 'Login successful.', 'result' => $result])
            ->header('Cache-Control', 'no-store');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->okWithResource(new UserResource($request->user()));
    }

    public function logout(): Response
    {
        $this->auth->logout();

        return $this->noContent();
    }

    public function verifyEmail(EmailVerificationRequest $request): Response
    {
        $this->auth->verifyEmail($request->user());

        return $this->noContent();
    }

    public function resendVerification(Request $request): Response
    {
        $this->auth->resendVerification($request->user());

        return $this->noContent();
    }
}
