<?php

namespace App\Core\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class BaseAuthenticatable
    extends Authenticatable
{
    protected $guarded = [];
}