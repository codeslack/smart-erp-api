<?php

namespace App\Modules\Payment\Services\Validation;

use App\Core\Exceptions\BusinessException;

use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;

use App\Modules\Payment\Services\Validation\Contracts\PaymentAllocationValidatorInterface;
use App\Modules\Payment\Services\Validation\Contracts\SupplierOpeningBillAllocationValidatorInterface;

class PaymentAllocationValidator
implements PaymentAllocationValidatorInterface
{
    private const CUSTOMER_OPENING_BILL = 'customer_opening_bill';
    private const SUPPLIER_OPENING_BILL = 'supplier_opening_bill';

    private const SCALE = 4;

    public function __construct(
        protected SupplierOpeningBillAllocationValidatorInterface $supplierOpeningBillValidator
    ) {}


    public function validate(
        Payment $payment,
        PaymentAllocation $allocation
    ): void {
        if (
            $allocation->document_type
            === self::CUSTOMER_OPENING_BILL
        ) {
            $this->validateCustomerOpeningBill(
                $payment,
                $allocation
            );

            return;
        }

        if (
            $allocation->document_type
            === self::SUPPLIER_OPENING_BILL
        ) {
            $this->supplierOpeningBillValidator->validate(
                $payment,
                $allocation
            );
        }
    }

    protected function validateCustomerOpeningBill(
        Payment $payment,
        PaymentAllocation $allocation
    ): void {
        $bill = CustomerOpeningBill::query()
            ->whereKey($allocation->document_id)
            ->first();

        if (! $bill) {
            throw new BusinessException(
                'Customer opening bill not found.'
            );
        }

        if ((int) $bill->tenant_id !== (int) tenantId()) {
            throw new BusinessException(
                'Customer opening bill does not belong to current tenant.'
            );
        }

        if (
            (int) $bill->customer_id
            !== (int) $payment->party_id
        ) {
            throw new BusinessException(
                'Customer opening bill does not belong to payment customer.'
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
                'Payment allocation exceeds opening bill balance.'
            );
        }
    }
}
