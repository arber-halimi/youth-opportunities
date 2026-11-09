<?php

namespace App\Http\Controllers;

use App\Http\Traits\Helpers\ApiResponseTrait;
use Illuminate\Routing\Controller;

abstract class ApiController extends Controller
{
    use ApiResponseTrait;
}
