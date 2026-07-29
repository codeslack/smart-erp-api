<?php

namespace App\Modules\Category;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Category\Providers\CategoryServiceProvider;
use App\Modules\Category\Services\CategorySetupService;

class CategoryModule
    implements ModuleInterface
{
    public static function name(): string
    {
        return 'Category';
    }

    public static function permissions(): array
    {
        return [

            'category.view',
            'category.create',
            'category.update',
            'category.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            CategoryServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [

            CategorySetupService::class,
        ];
    }
}