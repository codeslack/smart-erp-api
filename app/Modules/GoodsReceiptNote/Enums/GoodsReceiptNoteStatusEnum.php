<?php

namespace App\Modules\GoodsReceiptNote\Enums;

enum GoodsReceiptNoteStatusEnum: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::CONFIRMED => 'Confirmed',
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

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }
}