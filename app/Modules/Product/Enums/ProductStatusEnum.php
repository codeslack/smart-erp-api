<?php

namespace App\Modules\Product\Enums;

enum ProductStatusEnum: string
{
    case ACTIVE = 'ACTIVE';

    case INACTIVE = 'INACTIVE';
}