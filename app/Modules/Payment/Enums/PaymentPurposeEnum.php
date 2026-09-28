<?php

namespace App\Modules\Payment\Enums;

enum PaymentPurposeEnum: string
{
    case CUSTOMER_PAYMENT = 'customer_payment';
    case CUSTOMER_ADVANCE = 'customer_advance';
    case CUSTOMER_REFUND = 'customer_refund';

    case SUPPLIER_PAYMENT = 'supplier_payment';
    case SUPPLIER_ADVANCE = 'supplier_advance';
    case SUPPLIER_REFUND = 'supplier_refund';

    case OTHER_RECEIPT = 'other_receipt';
    case OTHER_PAYMENT = 'other_payment';

    public function isCustomerPayment(): bool
    {
        return $this === self::CUSTOMER_PAYMENT;
    }

    public function isCustomerAdvance(): bool
    {
        return $this === self::CUSTOMER_ADVANCE;
    }

    public function isCustomerRefund(): bool
    {
        return $this === self::CUSTOMER_REFUND;
    }

    public function isSupplierPayment(): bool
    {
        return $this === self::SUPPLIER_PAYMENT;
    }

    public function isSupplierAdvance(): bool
    {
        return $this === self::SUPPLIER_ADVANCE;
    }

    public function isSupplierRefund(): bool
    {
        return $this === self::SUPPLIER_REFUND;
    }

    public function isOtherReceipt(): bool
    {
        return $this === self::OTHER_RECEIPT;
    }

    public function isOtherPayment(): bool
    {
        return $this === self::OTHER_PAYMENT;
    }

    public function isReceipt(): bool
    {
        return match ($this) {
            self::CUSTOMER_PAYMENT,
            self::CUSTOMER_ADVANCE,
            self::SUPPLIER_REFUND,
            self::OTHER_RECEIPT => true,

            default => false,
        };
    }

    public function isPayment(): bool
    {
        return match ($this) {
            self::SUPPLIER_PAYMENT,
            self::SUPPLIER_ADVANCE,
            self::CUSTOMER_REFUND,
            self::OTHER_PAYMENT => true,

            default => false,
        };
    }
}