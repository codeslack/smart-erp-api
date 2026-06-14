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
];
