<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

abstract class AbstractJsonResource extends JsonResource
{
    abstract public function getModelName(): string;
}
