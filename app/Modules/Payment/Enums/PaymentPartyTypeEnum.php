<?php

namespace App\Modules\Payment\Enums;

enum PaymentPartyTypeEnum: string
{
    case CUSTOMER = 'customer';
    case SUPPLIER = 'supplier';
    case OTHER = 'other';

    public function isCustomer(): bool
    {
        return $this === self::CUSTOMER;
    }

    public function isSupplier(): bool
    {
        return $this === self::SUPPLIER;
    }

    public function isOther(): bool
    {
        return $this === self::OTHER;
    }
}
