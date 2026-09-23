<?php

namespace App\Modules\Accounting\Services\Contracts;

interface AccountingPostingServiceInterface
{
    public function postOpeningStock(
        mixed $openingStock
    ): void;

    public function postCustomerOpeningBalance(
        mixed $customer,
        mixed $openingBills
    ): void;

    public function postSupplierOpeningBalance(
        mixed $supplier,
        mixed $openingBills
    ): void;

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
