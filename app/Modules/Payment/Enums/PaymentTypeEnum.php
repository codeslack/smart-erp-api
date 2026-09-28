<?php

namespace App\Modules\Payment\Enums;

enum PaymentTypeEnum: string
{
    case RECEIPT = 'receipt';
    case PAYMENT = 'payment';

    public function isReceipt(): bool
    {
        return $this === self::RECEIPT;
    }

    public function isPayment(): bool
    {
        return $this === self::PAYMENT;
    }
}