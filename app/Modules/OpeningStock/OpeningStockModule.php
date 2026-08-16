<?php

namespace App\Modules\OpeningStock;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\OpeningStock\Providers\OpeningStockServiceProvider;

class OpeningStockModule
    implements ModuleInterface
{
    public static function name(): string
    {
        return 'OpeningStock';
    }

    public static function permissions(): array
    {
        return [

            'opening-stock.view',
            'opening-stock.create',
            'opening-stock.update',
            'opening-stock.delete',

            'opening-stock.post',
            'opening-stock.unpost',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            OpeningStockServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}