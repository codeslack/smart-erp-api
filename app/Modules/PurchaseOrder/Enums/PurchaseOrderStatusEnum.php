<?php

namespace App\Modules\PurchaseOrder\Enums;

enum PurchaseOrderStatusEnum: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case PARTIALLY_RECEIVED = 'partially_received';
    case FULLY_RECEIVED = 'fully_received';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::CONFIRMED => 'Confirmed',
            self::PARTIALLY_RECEIVED => 'Partially Received',
            self::FULLY_RECEIVED => 'Fully Received',
            self::CLOSED => 'Closed',
            self::CANCELLED => 'Cancelled',
        };
    }
}