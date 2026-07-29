<?php

namespace App\Modules\Brand;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Brand\Providers\BrandServiceProvider;
use App\Modules\Brand\Services\BrandSetupService;

class BrandModule
    implements ModuleInterface
{
    public static function name(): string
    {
        return 'Brand';
    }

    public static function permissions(): array
    {
        return [

            'brand.view',
            'brand.create',
            'brand.update',
            'brand.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            BrandServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [

            BrandSetupService::class,
        ];
    }
}