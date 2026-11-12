<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends ApiController
{
    public function __construct(private readonly PasswordResetService $passwords) {}

    public function sendLink(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwords->sendLink($request->validated('email'));

        return response()->json(['message' => 'If the account exists, a reset link will be sent.']);
    }

    public function reset(ResetPasswordRequest $request): Response
    {
        if (! $this->passwords->reset($request->validated())) {
            throw ValidationException::withMessages([
                'token' => 'The reset token is invalid or has expired.',
            ]);
        }

        return $this->noContent();
    }
}
