<?php

namespace App\Modules\Accounting\Services\Contracts;

use Illuminate\Database\Eloquent\Collection;

use App\Modules\Sales\Models\Sale;
use App\Modules\Payment\Models\Payment;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;

interface AccountingPostingServiceInterface
{
    public function postOpeningStock(
        OpeningStock $openingStock
    ): void;

    public function postCustomerOpeningBalance(
        Customer $customer,
        Collection $openingBills
    ): void;

    public function postSupplierOpeningBalance(
        Supplier $supplier,
        Collection $openingBills
    ): void;

    public function postSale(
        Sale $sale
    ): void;

    public function postPurchase(
        Purchase $purchase
    ): void;

    public function postPurchaseReturn(
        PurchaseReturn $purchaseReturn
    ): void;

    public function postSalesReturn(
        SalesReturn $salesReturn
    ): void;

    public function postPayment(
        Payment $payment
    ): void;
}