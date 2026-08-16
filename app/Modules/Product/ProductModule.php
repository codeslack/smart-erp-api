<?php

namespace App\Modules\Product;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Product\Providers\ProductServiceProvider;

class ProductModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Product';
    }

    public static function permissions(): array
    {
        return [

            'product.view',
            'product.create',
            'product.update',
            'product.delete',

            'product-variant.view',
            'product-variant.create',
            'product-variant.update',
            'product-variant.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            ProductServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}