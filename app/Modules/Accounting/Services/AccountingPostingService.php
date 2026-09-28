<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Database\Eloquent\Collection;

use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Sales\Models\Sale;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\Payment\Models\Payment;

use App\Modules\Accounting\Services\Postings\SalesPostingService;
use App\Modules\Accounting\Services\Postings\PurchasePostingService;
use App\Modules\Accounting\Services\Postings\PurchaseReturnPostingService;
use App\Modules\Accounting\Services\Postings\SalesReturnPostingService;
use App\Modules\Accounting\Services\Postings\OpeningStockAccountingPostingService;
use App\Modules\Accounting\Services\Postings\CustomerOpeningBalanceAccountingPostingService;
use App\Modules\Accounting\Services\Postings\SupplierOpeningBalanceAccountingPostingService;
use App\Modules\Accounting\Services\Postings\PaymentAccountingPostingService;

use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;

class AccountingPostingService implements AccountingPostingServiceInterface
{
    public function __construct(
        protected SalesPostingService $salePosting,
        protected PurchasePostingService $purchasePosting,
        protected PurchaseReturnPostingService $purchaseReturnPosting,
        protected SalesReturnPostingService $salesReturnPosting,
        protected OpeningStockAccountingPostingService $openingStockAccountPosting,
        protected CustomerOpeningBalanceAccountingPostingService $customerOpeningBalancePosting,
        protected SupplierOpeningBalanceAccountingPostingService $supplierOpeningBalancePosting,
        protected PaymentAccountingPostingService $paymentPosting,
    ) {}

    public function postOpeningStock(
        OpeningStock $openingStock
    ): void {
        $this->openingStockAccountPosting->post($openingStock);
    }

    public function postCustomerOpeningBalance(
        Customer $customer,
        Collection $openingBills
    ): void {
        $this->customerOpeningBalancePosting->post(
            $customer,
            $openingBills
        );
    }

    public function postSupplierOpeningBalance(
        Supplier $supplier,
        Collection $openingBills
    ): void {
        $this->supplierOpeningBalancePosting->post(
            $supplier,
            $openingBills
        );
    }

    public function postSale(
        Sale $sale
    ): void {
        $this->salePosting->post($sale);
    }

    public function postPurchase(
        Purchase $purchase
    ): void {
        $this->purchasePosting->post($purchase);
    }

    public function postPurchaseReturn(
        PurchaseReturn $purchaseReturn
    ): void {
        $this->purchaseReturnPosting->post($purchaseReturn);
    }

    public function postSalesReturn(
        SalesReturn $salesReturn
    ): void {
        $this->salesReturnPosting->post($salesReturn);
    }

    public function postPayment(
        Payment $payment
    ): void {
        $this->paymentPosting->post($payment);
    }
}