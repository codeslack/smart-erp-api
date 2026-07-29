<?php

namespace App\Core\Modules;

use App\Core\Modules\Contracts\ModuleInterface;

class ModuleRegistry
{
    /**
     * Registered modules.
     */
    public static function modules(): array
    {
        return config(
            'modules',
            []
        );
    }

    /**
     * Module names.
     */
    public static function moduleNames(): array
    {
        $names = [];

        foreach (
            self::modules()
            as $module
        ) {

            if (
                ! self::isValidModule(
                    $module
                )
            ) {
                continue;
            }

            $names[] =
                $module::name();
        }

        return $names;
    }

    /**
     * All module permissions.
     */
    public static function permissions(): array
    {
        $permissions = [];

        foreach (
            self::modules()
            as $module
        ) {

            if (
                ! self::isValidModule(
                    $module
                )
            ) {
                continue;
            }

            $permissions = array_merge(
                $permissions,
                $module::permissions()
            );
        }

        return array_values(
            array_unique(
                $permissions
            )
        );
    }

    /**
     * All module service providers.
     */
    public static function serviceProviders(): array
    {
        $providers = [];

        foreach (
            self::modules()
            as $module
        ) {

            if (
                ! self::isValidModule(
                    $module
                )
            ) {
                continue;
            }

            $providers = array_merge(
                $providers,
                $module::serviceProviders()
            );
        }

        return array_values(
            array_unique(
                $providers
            )
        );
    }

    /**
     * All tenant setup services.
     */
    public static function setupServices(): array
    {
        $services = [];

        foreach (
            self::modules()
            as $module
        ) {

            if (
                ! self::isValidModule(
                    $module
                )
            ) {
                continue;
            }

            $services = array_merge(
                $services,
                $module::setupServices()
            );
        }

        return array_values(
            array_unique(
                $services
            )
        );
    }

    protected static function isValidModule(
        string $module
    ): bool {

        return is_subclass_of(
            $module,
            ModuleInterface::class
        );
    }
}