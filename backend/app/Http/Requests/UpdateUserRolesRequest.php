<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateRoles', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array', 'list'],
            'roles.*' => ['required', 'string', 'max:255', 'distinct:strict'],
        ];
    }
}
