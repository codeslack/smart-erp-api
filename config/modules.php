<?php

/*
|--------------------------------------------------------------------------
| Application Modules Configuration
|--------------------------------------------------------------------------
|
| This file registers and manages all the modular sub-systems of the application.
| It acts as a central registry that the system loads during boot time.
|
| To temporarily disable a module, comment out its line.
|
*/

// filename: config/modules.php


return [

    /*
    |--------------------------------------------------------------------------
    | Core / Settings
    |--------------------------------------------------------------------------
    */

    App\Modules\Settings\SettingModule::class,

    /*
    |--------------------------------------------------------------------------
    | System / Identity
    |--------------------------------------------------------------------------
    */

    App\Modules\Tenant\TenantModule::class,
    App\Modules\User\UserModule::class,
    App\Modules\Rbac\RbacModule::class,

    /*
    |--------------------------------------------------------------------------
    | Master Data
    |--------------------------------------------------------------------------
    */

    App\Modules\Area\AreaModule::class,
    App\Modules\Brand\BrandModule::class,
    App\Modules\Category\CategoryModule::class,
    App\Modules\Unit\UnitModule::class,

    App\Modules\PaymentTerm\PaymentTermModule::class,
    App\Modules\Warehouse\WarehouseModule::class,

    /*
    |--------------------------------------------------------------------------
    | Parties
    |--------------------------------------------------------------------------
    */

    App\Modules\Customer\CustomerModule::class,
    App\Modules\Supplier\SupplierModule::class,
    App\Modules\CustomerOpeningBill\CustomerOpeningBillModule::class,

    /*
    |--------------------------------------------------------------------------
    | Product
    |--------------------------------------------------------------------------
    */

    App\Modules\Product\ProductModule::class,

    /*
    |--------------------------------------------------------------------------
    | Accounting
    |--------------------------------------------------------------------------
    */

    App\Modules\Accounting\AccountingModule::class,

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    App\Modules\Inventory\InventoryModule::class,

    /*
    |--------------------------------------------------------------------------
    | Opening Stock
    |--------------------------------------------------------------------------
    */

    App\Modules\OpeningStock\OpeningStockModule::class,

];
