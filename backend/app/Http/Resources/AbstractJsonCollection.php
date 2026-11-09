<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class AbstractJsonCollection extends ResourceCollection
{
    abstract public function getModelName(): string;
}
