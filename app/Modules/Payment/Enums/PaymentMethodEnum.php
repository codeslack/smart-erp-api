<?php

namespace App\Modules\Payment\Enums;

enum PaymentMethodEnum: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case CHEQUE = 'cheque';
    case UPI = 'upi';
    case CARD = 'card';
    case OTHER = 'other';

    public function isCash(): bool
    {
        return $this === self::CASH;
    }

    public function isBankTransfer(): bool
    {
        return $this === self::BANK_TRANSFER;
    }

    public function isCheque(): bool
    {
        return $this === self::CHEQUE;
    }

    public function isUpi(): bool
    {
        return $this === self::UPI;
    }

    public function isCard(): bool
    {
        return $this === self::CARD;
    }

    public function isOther(): bool
    {
        return $this === self::OTHER;
    }
}