<?php

namespace App\Http\Parsers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Contracts\Http\Parser;

class BearerTokenParser implements Parser
{
    public function parse(Request $request): ?string
    {
        $header = $request->headers->get('Authorization', '');

        return preg_match('/\ABearer +([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)\z/i', $header, $matches)
            ? $matches[1]
            : null;
    }
}
