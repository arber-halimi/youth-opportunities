<?php

namespace App\Http\Requests;

use App\Rules\PasswordByteLimit;

class RegisterRequest extends ForgotPasswordRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [...parent::rules()['email'], 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed', new PasswordByteLimit],
        ];
    }
}
