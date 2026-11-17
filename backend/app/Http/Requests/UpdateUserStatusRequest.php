<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('user'));
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in(['active', 'suspended'])]];
    }
}
