<?php

namespace App\Modules\Purchase\Enums;

enum PurchaseStatusEnum: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case PAID = 'paid';
    case PARTIALLY_PAID = 'partially_paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::CONFIRMED => 'Confirmed',
            self::PAID => 'Paid',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }
}

