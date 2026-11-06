<?php

namespace App\Modules\Payment\Domain;

use App\Core\Exceptions\BusinessException;
use App\Modules\Payment\Enums\PaymentPurposeEnum;

class PaymentAccountingRules
{
    public function requiresOffsetAccount(
        PaymentPurposeEnum $purpose,
    ): bool {
        return match ($purpose) {
            PaymentPurposeEnum::OTHER_RECEIPT,
            PaymentPurposeEnum::OTHER_PAYMENT => true,

            default => false,
        };
    }

    public function validateOffsetAccount(
        PaymentPurposeEnum $purpose,
        ?int $offsetAccountId,
    ): void {
        if (
            $this->requiresOffsetAccount($purpose)
            && empty($offsetAccountId)
        ) {
            throw new BusinessException(
                'Offset account is required.',
                'OFFSET_ACCOUNT_REQUIRED'
            );
        }
    }

    public function isDerivedAccount(
        PaymentPurposeEnum $purpose,
    ): bool {
        return match ($purpose) {
            PaymentPurposeEnum::CUSTOMER_PAYMENT,
            PaymentPurposeEnum::CUSTOMER_ADVANCE,
            PaymentPurposeEnum::CUSTOMER_OPENING_BALANCE_ADJUSTMENT,
            PaymentPurposeEnum::CUSTOMER_REFUND,

            PaymentPurposeEnum::SUPPLIER_PAYMENT,
            PaymentPurposeEnum::SUPPLIER_ADVANCE,
            PaymentPurposeEnum::SUPPLIER_REFUND => true,

            default => false,
        };
    }
}