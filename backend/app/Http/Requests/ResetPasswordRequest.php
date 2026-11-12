<?php

namespace App\Http\Requests;

use App\Rules\PasswordByteLimit;

class ResetPasswordRequest extends ForgotPasswordRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'token' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed', new PasswordByteLimit],
        ];
    }
}
