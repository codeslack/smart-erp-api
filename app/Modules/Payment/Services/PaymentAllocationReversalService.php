<?php

namespace App\Modules\Payment\Services;

use App\Core\Exceptions\BusinessException;

use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;

use App\Modules\Sales\Repositories\Contracts\SaleRepositoryInterface;
use App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface;

use App\Modules\CustomerOpeningBill\Repositories\Contracts\CustomerOpeningBillRepositoryInterface;
use App\Modules\SupplierOpeningBill\Repositories\Contracts\SupplierOpeningBillRepositoryInterface;

use App\Modules\Payment\Services\Contracts\PaymentAllocationReversalServiceInterface;

class PaymentAllocationReversalService
    implements PaymentAllocationReversalServiceInterface
{
    private const CUSTOMER_OPENING_BILL = 'customer_opening_bill';

    private const SUPPLIER_OPENING_BILL = 'supplier_opening_bill';

    private const SALES_INVOICE = 'sales_invoice';

    private const PURCHASE_BILL = 'purchase_bill';

    private const SCALE = 4;

    public function __construct(
        protected CustomerOpeningBillRepositoryInterface $customerOpeningBills,
        protected SupplierOpeningBillRepositoryInterface $supplierOpeningBills,
        protected SaleRepositoryInterface $sales,
        protected PurchaseRepositoryInterface $purchases,
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

        if ($documentType === self::CUSTOMER_OPENING_BILL) {
            if ($payment->party_type !== PaymentPartyTypeEnum::CUSTOMER) {
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

        if ($documentType === self::SUPPLIER_OPENING_BILL) {
            if ($payment->party_type !== PaymentPartyTypeEnum::SUPPLIER) {
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

        if ($documentType === self::SALES_INVOICE) {
            $this->reverseSalesInvoice($payment, $allocation);

            return;
        }

        if ($documentType === self::PURCHASE_BILL) {
            $this->reversePurchaseBill($payment, $allocation);

            return;
        }

        throw new BusinessException(
            'Unsupported payment allocation document type.'
        );
    }

    protected function reverseSalesInvoice(
        Payment $payment,
        PaymentAllocation $allocation
    ): void {
        if ($payment->party_type !== PaymentPartyTypeEnum::CUSTOMER) {
            throw new BusinessException(
                'Sales invoice reversal requires a customer payment.'
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

        $allocatedAmount = bcadd(
            (string) $allocation->allocated_amount,
            '0',
            self::SCALE
        );

        $paidAmount = bcsub(
            (string) $sale->paid_amount,
            $allocatedAmount,
            self::SCALE
        );

        if (bccomp($paidAmount, '0', self::SCALE) < 0) {
            throw new BusinessException(
                'Payment reversal would make the sales invoice paid amount negative.'
            );
        }

        $dueAmount = bcadd(
            (string) $sale->due_amount,
            $allocatedAmount,
            self::SCALE
        );

        $this->sales->update($sale, [
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
        ]);
    }

    protected function reversePurchaseBill(
        Payment $payment,
        PaymentAllocation $allocation
    ): void {
        if ($payment->party_type !== PaymentPartyTypeEnum::SUPPLIER) {
            throw new BusinessException(
                'Purchase bill reversal requires a supplier payment.'
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

        $allocatedAmount = bcadd(
            (string) $allocation->allocated_amount,
            '0',
            self::SCALE
        );

        $paidAmount = bcsub(
            (string) $purchase->paid_amount,
            $allocatedAmount,
            self::SCALE
        );

        if (bccomp($paidAmount, '0', self::SCALE) < 0) {
            throw new BusinessException(
                'Payment reversal would make the purchase bill paid amount negative.'
            );
        }

        $dueAmount = bcadd(
            (string) $purchase->due_amount,
            $allocatedAmount,
            self::SCALE
        );

        $this->purchases->update($purchase, [
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
        ]);
    }
}
