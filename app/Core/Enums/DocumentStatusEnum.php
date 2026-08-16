<?php

namespace App\Core\Enums;

enum DocumentStatusEnum: string
{
    /*
    |--------------------------------------------------------------------------
    | Draft
    |--------------------------------------------------------------------------
    |
    | Document is being prepared.
    | No accounting posting.
    | No inventory posting.
    |
    */

    case DRAFT = 'DRAFT';

    /*
    |--------------------------------------------------------------------------
    | Confirmed
    |--------------------------------------------------------------------------
    |
    | Business transaction completed.
    | Inventory and/or accounting posting allowed.
    |
    */

    case CONFIRMED = 'CONFIRMED';

    /*
    |--------------------------------------------------------------------------
    | Cancelled
    |--------------------------------------------------------------------------
    |
    | Document cancelled.
    | No further modifications allowed.
    |
    */

    case CANCELLED = 'CANCELLED';
}