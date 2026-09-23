<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Services\Postings\SalesPostingService;
use App\Modules\Accounting\Services\Postings\PurchasePostingService;
use App\Modules\Accounting\Services\Postings\ReceiptPostingService;
use App\Modules\Accounting\Services\Postings\PaymentPostingService;
use App\Modules\Accounting\Services\Postings\PurchaseReturnPostingService;
use App\Modules\Accounting\Services\Postings\SalesReturnPostingService;
use App\Modules\Accounting\Services\Postings\OpeningStockAccountingPostingService;
use App\Modules\Accounting\Services\Postings\CustomerOpeningBalanceAccountingPostingService;
use App\Modules\Accounting\Services\Postings\SupplierOpeningBalanceAccountingPostingService;

use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;

class AccountingPostingService
    implements AccountingPostingServiceInterface
{
    public function __construct(
        protected SalesPostingService $salePosting,
        protected PurchasePostingService $purchasePosting,
        protected ReceiptPostingService $customerReceiptPosting,
        protected PaymentPostingService $supplierPaymentPosting,
        protected PurchaseReturnPostingService $purchaseReturnPosting,
        protected SalesReturnPostingService $salesReturnPosting,
        protected OpeningStockAccountingPostingService $openingStockAccountPosting,
        protected CustomerOpeningBalanceAccountingPostingService $customerOpeningBalancePosting,
        protected SupplierOpeningBalanceAccountingPostingService $supplierOpeningBalancePosting,
    ) {}

    public function postOpeningStock(
        mixed $openingStock
    ): void {
        $this->openingStockAccountPosting
            ->post($openingStock);
    }

    public function postCustomerOpeningBalance(
        mixed $customer,
        mixed $openingBills
    ): void {
        $this->customerOpeningBalancePosting
            ->post(
                $customer,
                $openingBills
            );
    }

    public function postSupplierOpeningBalance(
        mixed $supplier,
        mixed $openingBills
    ): void {
        $this->supplierOpeningBalancePosting
            ->post(
                $supplier,
                $openingBills
            );
    }

    public function postSale(
        mixed $sale
    ): void {
        $this->salePosting->post($sale);
    }

    public function postPurchase(
        mixed $purchase
    ): void {
        $this->purchasePosting->post($purchase);
    }

    public function postCustomerReceipt(
        mixed $receipt
    ): void {
        $this->customerReceiptPosting->post($receipt);
    }

    public function postSupplierPayment(
        mixed $payment
    ): void {
        $this->supplierPaymentPosting->post($payment);
    }

    public function postPurchaseReturn(
        mixed $purchaseReturn
    ): void {
        $this->purchaseReturnPosting->post($purchaseReturn);
    }

    public function postSalesReturn(
        mixed $salesReturn
    ): void {
        $this->salesReturnPosting->post($salesReturn);
    }
}