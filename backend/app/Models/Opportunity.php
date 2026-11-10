<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'deadline_at' => 'datetime',
        ];
    }
}
