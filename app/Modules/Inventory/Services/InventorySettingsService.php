<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Settings\Services\SettingService;
use App\Modules\Settings\Enums\SettingGroupEnum;
use App\Modules\Settings\Enums\InventoryCostingMethodEnum;

class InventorySettingsService
{
    public function __construct(
        protected SettingService $settings
    ) {}

    public function costingMethod(): InventoryCostingMethodEnum
    {
        $value = $this->settings->get(
            SettingGroupEnum::INVENTORY->value,
            'costing_method',
            InventoryCostingMethodEnum::WEIGHTED_AVERAGE->value
        );

        return InventoryCostingMethodEnum::from(
            $value
        );
    }

    public function isWeightedAverage(): bool
    {
        return $this->costingMethod()
            === InventoryCostingMethodEnum::WEIGHTED_AVERAGE;
    }

    public function isFifo(): bool
    {
        return $this->costingMethod()
            === InventoryCostingMethodEnum::FIFO;
    }

    public function allowNegativeStock(): bool
    {
        return filter_var(
            $this->settings->get(
                SettingGroupEnum::INVENTORY->value,
                'allow_negative_stock',
                false
            ),
            FILTER_VALIDATE_BOOL
        );
    }
}