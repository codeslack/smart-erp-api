<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    App\Modules\Rbac\Providers\RbacServiceProvider::class,
    App\Modules\Unit\Providers\UnitServiceProvider::class,
    App\Modules\Category\Providers\CategoryServiceProvider::class,
    App\Modules\Brand\Providers\BrandServiceProvider::class,
    App\Modules\Warehouse\Providers\WarehouseServiceProvider::class,
    App\Modules\Product\Providers\ProductServiceProvider::class,
    App\Modules\Inventory\Providers\InventoryServiceProvider::class,
    App\Modules\Supplier\Providers\SupplierServiceProvider::class,
    App\Modules\Customer\Providers\CustomerServiceProvider::class,
    App\Modules\Purchase\Providers\PurchaseServiceProvider::class,
    App\Modules\Sales\Providers\SaleServiceProvider::class,
    App\Modules\PurchaseReturn\Providers\PurchaseReturnServiceProvider::class,
    App\Modules\SalesReturn\Providers\SalesReturnServiceProvider::class,
    App\Modules\StockAdjustment\Providers\StockAdjustmentServiceProvider::class,
    App\Modules\StockTransfer\Providers\StockTransferServiceProvider::class,
    App\Modules\PurchaseOrder\Providers\PurchaseOrderServiceProvider::class,
    App\Modules\SalesQuotation\Providers\SalesQuotationServiceProvider::class,
    App\Modules\SalesOrder\Providers\SalesOrderServiceProvider::class,
    App\Modules\GoodsReceiptNote\Providers\GoodsReceiptNoteServiceProvider::class,
    App\Modules\DeliveryNote\Providers\DeliveryNoteServiceProvider::class,
    App\Modules\CustomerReceipt\Providers\CustomerReceiptServiceProvider::class,
    App\Modules\SupplierPayment\Providers\SupplierPaymentServiceProvider::class,

];
