<?php

namespace App\Modules\Payment\Domain;

use App\Core\Exceptions\BusinessException;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;

class PaymentTransactionRules
{
    public function validate(
        PaymentTypeEnum $type,
        PaymentPartyTypeEnum $partyType,
        PaymentPurposeEnum $purpose,
    ): void {
        if (! $this->isValidCombination($type, $partyType, $purpose)) {
            throw new BusinessException(
                'Invalid payment transaction type, party type, and purpose combination.',
                'INVALID_PAYMENT_TRANSACTION'
            );
        }
    }

    protected function isValidCombination(
        PaymentTypeEnum $type,
        PaymentPartyTypeEnum $partyType,
        PaymentPurposeEnum $purpose,
    ): bool {
        return match ($partyType) {
            PaymentPartyTypeEnum::CUSTOMER => $this->isValidCustomerTransaction(
                $type,
                $purpose
            ),

            PaymentPartyTypeEnum::SUPPLIER => $this->isValidSupplierTransaction(
                $type,
                $purpose
            ),

            PaymentPartyTypeEnum::OTHER => $this->isValidOtherTransaction(
                $type,
                $purpose
            ),
        };
    }

    protected function isValidCustomerTransaction(
        PaymentTypeEnum $type,
        PaymentPurposeEnum $purpose,
    ): bool {
        return match ($purpose) {
            PaymentPurposeEnum::CUSTOMER_PAYMENT,
            PaymentPurposeEnum::CUSTOMER_ADVANCE,
            PaymentPurposeEnum::CUSTOMER_OPENING_BALANCE_ADJUSTMENT =>
                $type->isReceipt(),

            PaymentPurposeEnum::CUSTOMER_REFUND =>
                $type->isPayment(),

            default => false,
        };
    }

    protected function isValidSupplierTransaction(
        PaymentTypeEnum $type,
        PaymentPurposeEnum $purpose,
    ): bool {
        return match ($purpose) {
            PaymentPurposeEnum::SUPPLIER_PAYMENT,
            PaymentPurposeEnum::SUPPLIER_ADVANCE =>
                $type->isPayment(),

            PaymentPurposeEnum::SUPPLIER_REFUND =>
                $type->isReceipt(),

            default => false,
        };
    }

    protected function isValidOtherTransaction(
        PaymentTypeEnum $type,
        PaymentPurposeEnum $purpose,
    ): bool {
        return match ($purpose) {
            PaymentPurposeEnum::OTHER_RECEIPT =>
                $type->isReceipt(),

            PaymentPurposeEnum::OTHER_PAYMENT =>
                $type->isPayment(),

            default => false,
        };
    }
}
