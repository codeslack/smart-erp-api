<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | This is where you can register your modules for the application.
    |
    */
    App\Modules\Settings\SettingModule::class,


    /*
    |--------------------------------------------------------------------------
    | Master Data
    |--------------------------------------------------------------------------
    */
    App\Modules\Area\AreaModule::class,

    App\Modules\Brand\BrandModule::class,

    App\Modules\Category\CategoryModule::class,

    App\Modules\Unit\UnitModule::class,

    App\Modules\Warehouse\WarehouseModule::class,

    App\Modules\Product\ProductModule::class,


    /*
    |--------------------------------------------------------------------------
    | Transaction
    |--------------------------------------------------------------------------
    */
    App\Modules\Inventory\InventoryModule::class,
    App\Modules\OpeningStock\OpeningStockModule::class,

];