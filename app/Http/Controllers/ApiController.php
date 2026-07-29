<?php

namespace App\Http\Controllers;

use App\Core\Traits\ApiResponse;

abstract class ApiController extends Controller
{
    use ApiResponse;
}