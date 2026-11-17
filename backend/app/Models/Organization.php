<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Organization extends Model
{
    protected $fillable = ['name', 'description'];

    protected $attributes = ['status' => OrganizationStatus::Pending->value];

    protected function casts(): array
    {
        return ['status' => OrganizationStatus::class];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('role')
            ->withTimestamps();
    }
}
