<?php

namespace App\Core\Modules\Contracts;

interface ModuleInterface
{
    /**
     * Module display name.
     */
    public static function name(): string;

    /**
     * Module permissions.
     *
     * Example:
     * [
     *     'unit.view',
     *     'unit.create',
     * ]
     */
    public static function permissions(): array;

    /**
     * Module service providers.
     *
     * Example:
     * [
     *     UnitServiceProvider::class,
     * ]
     */
    public static function serviceProviders(): array;

    /**
     * Tenant setup services.
     *
     * Example:
     * [
     *     UnitSetupService::class,
     * ]
     */
    public static function setupServices(): array;
}