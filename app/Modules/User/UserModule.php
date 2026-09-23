<?php

namespace App\Modules\User;

use App\Core\Modules\Contracts\ModuleInterface;
use App\Modules\User\Providers\UserServiceProvider;

class UserModule implements ModuleInterface
{
    public static function name(): string
    {
        return 'User';
    }

    public static function permissions(): array
    {
        return [
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
        ];
    }

    public static function serviceProviders(): array
    {
        return [
            UserServiceProvider::class,
        ];
    }

    public static function setupServices(): array
    {
        return [];
    }
}