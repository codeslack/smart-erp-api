<?php

namespace App\Modules\Payment\Services;

use App\Core\Exceptions\BusinessException;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;

use App\Modules\CustomerOpeningBill\Repositories\Contracts\CustomerOpeningBillRepositoryInterface;
use App\Modules\SupplierOpeningBill\Repositories\Contracts\SupplierOpeningBillRepositoryInterface;

use App\Modules\Payment\Services\Contracts\PaymentAllocationReversalServiceInterface;

class PaymentAllocationReversalService
    implements PaymentAllocationReversalServiceInterface
{
    private const CUSTOMER_OPENING_BILL = 'customer_opening_bill';

    private const SUPPLIER_OPENING_BILL = 'supplier_opening_bill';

    public function __construct(
        protected CustomerOpeningBillRepositoryInterface $customerOpeningBills,
        protected SupplierOpeningBillRepositoryInterface $supplierOpeningBills,
    ) {}

    public function reverse(
        Payment $payment
    ): void {
        foreach ($payment->allocations as $allocation) {
            $this->reverseAllocation(
                $payment,
                $allocation
            );
        }
    }

    protected function reverseAllocation(
        Payment $payment,
        PaymentAllocation $allocation
    ): void {
        $documentType = $allocation->document_type;

        if (
            $documentType === self::CUSTOMER_OPENING_BILL
        ) {
            if (
                $payment->party_type
                !== PaymentPartyTypeEnum::CUSTOMER
            ) {
                throw new BusinessException(
                    'Customer opening bill reversal requires a customer payment.'
                );
            }

            $openingBill = $this->customerOpeningBills->findById(
                $allocation->document_id
            );

            if (! $openingBill) {
                throw new BusinessException(
                    'Customer opening bill not found.'
                );
            }

            $this->customerOpeningBills->incrementBalance(
                $openingBill,
                (string) $allocation->allocated_amount
            );

            return;
        }

        if (
            $documentType === self::SUPPLIER_OPENING_BILL
        ) {
            if (
                $payment->party_type
                !== PaymentPartyTypeEnum::SUPPLIER
            ) {
                throw new BusinessException(
                    'Supplier opening bill reversal requires a supplier payment.'
                );
            }

            $openingBill = $this->supplierOpeningBills->findById(
                $allocation->document_id
            );

            if (! $openingBill) {
                throw new BusinessException(
                    'Supplier opening bill not found.'
                );
            }

            $this->supplierOpeningBills->incrementBalance(
                $openingBill,
                (string) $allocation->allocated_amount
            );

            return;
        }
    }
}