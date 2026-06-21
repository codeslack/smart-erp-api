<?php

namespace App\Modules\SalesOrder\Enums;

class SalesOrderStatus
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const CONVERTED_TO_DELIVERY = 'converted_to_delivery';

    public const CONVERTED_TO_SALE = 'converted_to_sale';

    public const CANCELLED = 'cancelled';
}
