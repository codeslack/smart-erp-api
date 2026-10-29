<?php

namespace App\Modules\Purchase\Enums;

class PurchaseStatus
{
    public const DRAFT = 'draft';
    public const CONFIRMED = 'confirmed';
    public const PAID = 'paid';
    public const PARTIALLY_PAID = 'partially_paid';
    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::DRAFT,
            self::CONFIRMED,
            self::PAID,
            self::PARTIALLY_PAID,
            self::CANCELLED,
        ];
    }
}
