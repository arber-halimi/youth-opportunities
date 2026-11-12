<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends AbstractJsonResource
{
    public function getModelName(): string
    {
        return 'User';
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
        ];
    }
}
