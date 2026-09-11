<?php

namespace App\Modules\Settings;

use App\Core\Modules\Contracts\ModuleInterface;

use App\Modules\Settings\Providers\SettingServiceProvider;
use App\Modules\Settings\Services\SettingsSetupService;

class SettingModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'Settings';
    }

    public static function permissions(): array
    {
        return [

            'Settings.view',
            'Settings.create',
            'Settings.update',
            'Settings.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [

            SettingServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [

            SettingsSetupService::class,
        ];
    }
}