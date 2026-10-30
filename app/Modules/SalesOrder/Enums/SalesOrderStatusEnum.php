<?php

namespace App\Modules\SalesOrder\Enums;

enum SalesOrderStatusEnum: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case PARTIALLY_DELIVERED = 'partially_delivered';
    case DELIVERED = 'delivered';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::CONFIRMED => 'Confirmed',
            self::PARTIALLY_DELIVERED => 'Partially Delivered',
            self::DELIVERED => 'Delivered',
            self::CLOSED => 'Closed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function canBeUpdated(): bool
    {
        return $this === self::DRAFT;
    }

    public function canBeConfirmed(): bool
    {
        return $this === self::DRAFT;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::CONFIRMED,
        ], true);
    }
}
