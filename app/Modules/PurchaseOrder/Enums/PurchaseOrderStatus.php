<?php

namespace App\Modules\PurchaseOrder\Enums;

class PurchaseOrderStatus
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const CONVERTED = 'converted';

    public const CANCELLED = 'cancelled';
}