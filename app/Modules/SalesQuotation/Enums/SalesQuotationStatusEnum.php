<?php

namespace App\Modules\SalesQuotation\Enums;

enum SalesQuotationStatusEnum: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CONVERTED_TO_ORDER = 'converted_to_order';
    case CONVERTED_TO_SALE = 'converted_to_sale';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SENT => 'Sent',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::CONVERTED_TO_ORDER => 'Converted to Order',
            self::CONVERTED_TO_SALE => 'Converted to Sale',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT], true);
    }

    public function canSend(): bool
    {
        return $this === self::DRAFT;
    }

    public function canAccept(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT], true);
    }

    public function canReject(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT], true);
    }

    public function canConvert(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT, self::ACCEPTED], true);
    }

    public function canCancel(): bool
    {
        return ! in_array($this, [
            self::CONVERTED_TO_ORDER,
            self::CONVERTED_TO_SALE,
            self::CANCELLED,
        ], true);
    }
}
