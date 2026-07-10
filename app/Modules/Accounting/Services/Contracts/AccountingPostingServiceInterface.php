<?php

namespace App\Modules\Accounting\Services\Contracts;

interface AccountingPostingServiceInterface
{
    public function postSale(
        mixed $sale
    ): void;

    public function postPurchase(
        mixed $purchase
    ): void;

    public function postCustomerReceipt(
        mixed $receipt
    ): void;

    public function postSupplierPayment(
        mixed $payment
    ): void;

    public function postPurchaseReturn(
        mixed $purchaseReturn
    ): void;

    public function postSalesReturn(
        mixed $salesReturn
    ): void;
}
