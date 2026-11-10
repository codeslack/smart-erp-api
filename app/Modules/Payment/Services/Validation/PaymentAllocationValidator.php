<?php

namespace App\Modules\Payment\Services\Validation;

use App\Core\Exceptions\BusinessException;

use App\Modules\CustomerOpeningBill\Models\CustomerOpeningBill;

use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;

use App\Modules\Sales\Repositories\Contracts\SaleRepositoryInterface;
use App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface;

use App\Modules\Payment\Services\Validation\Contracts\PaymentAllocationValidatorInterface;
use App\Modules\Payment\Services\Validation\Contracts\SupplierOpeningBillAllocationValidatorInterface;

class PaymentAllocationValidator
    implements PaymentAllocationValidatorInterface
{
    private const CUSTOMER_OPENING_BILL = 'customer_opening_bill';

    private const SUPPLIER_OPENING_BILL = 'supplier_opening_bill';

    private const SALES_INVOICE = 'sales_invoice';

    private const PURCHASE_BILL = 'purchase_bill';

    private const SCALE = 4;

    public function __construct(
        protected SupplierOpeningBillAllocationValidatorInterface $supplierOpeningBillValidator,
        protected SaleRepositoryInterface $sales,
        protected PurchaseRepositoryInterface $purchases,
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

            return;
        }

        if (
            $allocation->document_type
            === self::SALES_INVOICE
        ) {
            $this->validateSalesInvoice(
                $payment,
                $allocation
            );

            return;
        }

        if (
            $allocation->document_type
            === self::PURCHASE_BILL
        ) {
            $this->validatePurchaseBill(
                $payment,
                $allocation
            );

            return;
        }

        throw new BusinessException(
            'Unsupported payment allocation document type.'
        );
    }

    protected function validateCustomerOpeningBill(
        Payment $payment,
        PaymentAllocation $allocation
    ): void {
        if (
            $payment->party_type
            !== PaymentPartyTypeEnum::CUSTOMER
        ) {
            throw new BusinessException(
                'Customer opening bill allocation requires a customer payment.'
            );
        }

        $bill = CustomerOpeningBill::query()
            ->whereKey($allocation->document_id)
            ->first();

        if (! $bill) {
            throw new BusinessException(
                'Customer opening bill not found.'
            );
        }

        if (
            (int) $bill->tenant_id
            !== (int) tenantId()
        ) {
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

        $this->validateOutstandingBalance(
            $allocation,
            (string) $bill->balance_amount,
            'Payment allocation exceeds opening bill balance.'
        );
    }

    protected function validateSalesInvoice(
        Payment $payment,
        PaymentAllocation $allocation
    ): void {
        if (
            $payment->party_type
            !== PaymentPartyTypeEnum::CUSTOMER
        ) {
            throw new BusinessException(
                'Sales invoice allocation requires a customer payment.'
            );
        }

        $sale = $this->sales->findByIdForUpdate(
            (int) $allocation->document_id
        );

        if (! $sale) {
            throw new BusinessException(
                'Sales invoice not found.'
            );
        }

        if (
            (int) $sale->tenant_id
            !== (int) tenantId()
        ) {
            throw new BusinessException(
                'Sales invoice does not belong to current tenant.'
            );
        }

        if (
            (int) $sale->customer_id
            !== (int) $payment->party_id
        ) {
            throw new BusinessException(
                'Sales invoice does not belong to payment customer.'
            );
        }

        if (! $sale->isConfirmed()) {
            throw new BusinessException(
                'Only confirmed sales invoices can be allocated.'
            );
        }

        $this->validateOutstandingBalance(
            $allocation,
            (string) $sale->due_amount,
            'Payment allocation exceeds sales invoice balance.'
        );
    }

    protected function validatePurchaseBill(
        Payment $payment,
        PaymentAllocation $allocation
    ): void {
        if (
            $payment->party_type
            !== PaymentPartyTypeEnum::SUPPLIER
        ) {
            throw new BusinessException(
                'Purchase bill allocation requires a supplier payment.'
            );
        }

        $purchase = $this->purchases->findByIdForUpdate(
            (int) $allocation->document_id
        );

        if (! $purchase) {
            throw new BusinessException(
                'Purchase bill not found.'
            );
        }

        if (
            (int) $purchase->tenant_id
            !== (int) tenantId()
        ) {
            throw new BusinessException(
                'Purchase bill does not belong to current tenant.'
            );
        }

        if (
            (int) $purchase->supplier_id
            !== (int) $payment->party_id
        ) {
            throw new BusinessException(
                'Purchase bill does not belong to payment supplier.'
            );
        }

        if (! $purchase->isConfirmed()) {
            throw new BusinessException(
                'Only confirmed purchase bills can be allocated.'
            );
        }

        $this->validateOutstandingBalance(
            $allocation,
            (string) $purchase->due_amount,
            'Payment allocation exceeds purchase bill balance.'
        );
    }

    protected function validateOutstandingBalance(
        PaymentAllocation $allocation,
        string $balance,
        string $message
    ): void {
        $allocatedAmount = bcadd(
            (string) $allocation->allocated_amount,
            '0',
            self::SCALE
        );

        $balanceAmount = bcadd(
            $balance,
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
            throw new BusinessException($message);
        }
    }
}
