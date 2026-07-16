<?php

namespace App\Modules\PurchaseReturn\Enums;

enum PurchaseReturnStatus: string
{
    case DRAFT = 'draft';

    case CONFIRMED = 'confirmed';

    case CANCELLED = 'cancelled';
}