<?php

namespace App\Modules\Payment\Services;

use App\Core\Exceptions\BusinessException;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;

use App\Modules\CustomerOpeningBill\Repositories\Contracts\CustomerOpeningBillRepositoryInterface;
use App\Modules\SupplierOpeningBill\Repositories\Contracts\SupplierOpeningBillRepositoryInterface;

use App\Modules\Payment\Services\Contracts\PaymentAllocationPostingServiceInterface;

use App\Modules\Payment\Services\Validation\Contracts\PaymentAllocationValidatorInterface;
use App\Modules\Payment\Services\Validation\Contracts\SupplierOpeningBillAllocationValidatorInterface;

class PaymentAllocationPostingService
    implements PaymentAllocationPostingServiceInterface
{
    private const CUSTOMER_OPENING_BILL = 'customer_opening_bill';

    private const SUPPLIER_OPENING_BILL = 'supplier_opening_bill';

    public function __construct(
        protected CustomerOpeningBillRepositoryInterface $customerOpeningBills,
        protected SupplierOpeningBillRepositoryInterface $supplierOpeningBills,
        protected PaymentAllocationValidatorInterface $customerValidator,
        protected SupplierOpeningBillAllocationValidatorInterface $supplierValidator,
    ) {}

    public function post(
        Payment $payment
    ): void {
        foreach ($payment->allocations as $allocation) {
            $this->postAllocation(
                $payment,
                $allocation
            );
        }
    }

    protected function postAllocation(
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
                    'Customer opening bill allocation requires a customer payment.'
                );
            }

            $this->customerValidator->validate(
                $payment,
                $allocation
            );

            $openingBill = $this->customerOpeningBills->findById(
                $allocation->document_id
            );

            if (! $openingBill) {
                throw new BusinessException(
                    'Customer opening bill not found.'
                );
            }

            $this->customerOpeningBills->decrementBalance(
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
                    'Supplier opening bill allocation requires a supplier payment.'
                );
            }

            $this->supplierValidator->validate(
                $payment,
                $allocation
            );

            $openingBill = $this->supplierOpeningBills->findById(
                $allocation->document_id
            );

            if (! $openingBill) {
                throw new BusinessException(
                    'Supplier opening bill not found.'
                );
            }

            $this->supplierOpeningBills->decrementBalance(
                $openingBill,
                (string) $allocation->allocated_amount
            );

            return;
        }
    }
}