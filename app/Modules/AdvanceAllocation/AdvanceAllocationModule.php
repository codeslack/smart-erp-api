<?php

namespace App\Modules\AdvanceAllocation;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\AdvanceAllocation\Providers\AdvanceAllocationServiceProvider;

class AdvanceAllocationModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'AdvanceAllocation';
    }

    public static function permissions(): array
    {
        return [];
    }

    public static function serviceProviders(): array
    {
        return [
            AdvanceAllocationServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}

