<?php

namespace App\Modules\Accounting\Services\Contracts;

interface AccountingPostingServiceInterface
{
    public function postSale(
        mixed $sale
    );

    public function postPurchase(
        mixed $purchase
    );

    public function postCustomerReceipt(
        mixed $receipt
    );

    public function postSupplierPayment(
        mixed $payment
    );

    public function postPurchaseReturn(
        mixed $purchaseReturn
    );

    public function postSalesReturn(
        mixed $salesReturn
    );
}