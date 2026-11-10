<?php

namespace App\Modules\Payment\Services\Validation;

use App\Core\Exceptions\BusinessException;

use App\Modules\Payment\Enums\PaymentPartyTypeEnum;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;

use App\Modules\Payment\Services\Validation\Contracts\SupplierOpeningBillAllocationValidatorInterface;

use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;

class SupplierOpeningBillAllocationValidator
implements SupplierOpeningBillAllocationValidatorInterface
{
    private const SCALE = 4;

    public function validate(
        Payment $payment,
        PaymentAllocation $allocation
    ): void {
        if (
            $payment->party_type
            !== PaymentPartyTypeEnum::SUPPLIER
        ) {
            throw new BusinessException(
                'Supplier opening bill allocation requires a supplier payment.'
            );
        }

        $bill = SupplierOpeningBill::query()
            ->whereKey($allocation->document_id)
            ->first();

        if (! $bill) {
            throw new BusinessException(
                'Supplier opening bill not found.'
            );
        }

        if (
            (int) $bill->tenant_id
            !== (int) tenantId()
        ) {
            throw new BusinessException(
                'Supplier opening bill does not belong to current tenant.'
            );
        }

        if (
            (int) $bill->supplier_id
            !== (int) $payment->party_id
        ) {
            throw new BusinessException(
                'Supplier opening bill does not belong to payment supplier.'
            );
        }

        $allocatedAmount = bcadd(
            (string) $allocation->allocated_amount,
            '0',
            self::SCALE
        );

        $balanceAmount = bcadd(
            (string) $bill->balance_amount,
            '0',
            self::SCALE
        );

        if (
            bccomp(
                $allocatedAmount,
                $balanceAmount,
                self::SCALE
            ) > 0
        ) {
            throw new BusinessException(
                'Payment allocation exceeds supplier opening bill balance.'
            );
        }
    }
}
