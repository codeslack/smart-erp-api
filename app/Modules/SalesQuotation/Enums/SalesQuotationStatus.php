<?php

namespace App\Modules\SalesQuotation\Enums;

class SalesQuotationStatus
{
    public const DRAFT = 'draft';
    public const SENT = 'sent';
    public const APPROVED = 'approved';
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const CONVERTED_TO_ORDER = 'converted_to_order';
    public const CONVERTED_TO_SALE = 'converted_to_sale';
    public const EXPIRED = 'expired';
    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return array_column(SalesQuotationStatusEnum::cases(), 'value');
    }
}
