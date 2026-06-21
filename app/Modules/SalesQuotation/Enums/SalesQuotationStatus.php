<?php

namespace App\Modules\SalesQuotation\Enums;

class SalesQuotationStatus
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const CONVERTED_TO_ORDER = 'converted_to_order';

    public const CONVERTED_TO_SALE = 'converted_to_sale';

    public const CANCELLED = 'cancelled';
}
