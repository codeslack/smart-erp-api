<?php

namespace App\Modules\Settings\Services;

use App\Core\Tenant\TenantManager;

use App\Modules\Settings\Enums\InventoryCostingMethodEnum;
use App\Modules\Settings\Enums\SettingGroupEnum;
use App\Modules\Settings\Models\Setting;

use App\Modules\Tenant\Enums\BusinessTypeEnum;
use App\Modules\Tenant\Models\Tenant;

class SettingsSetupService
{
    public function __construct(
        protected TenantManager $tenantManager
    ) {}

    public function setup(
        Tenant $tenant
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Establish Tenant Context
        |--------------------------------------------------------------------------
        */

        $previousTenant =
            $this->tenantManager->getTenant();

        $this->tenantManager->setTenant(
            $tenant
        );

        try {

            /*
            |--------------------------------------------------------------------------
            | Already Configured
            |--------------------------------------------------------------------------
            */

            if (
                Setting::query()
                    ->where(
                        'tenant_id',
                        $tenant->id
                    )
                    ->exists()
            ) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Create Default Settings
            |--------------------------------------------------------------------------
            */

            foreach (
                $this->defaultSettings($tenant)
                as $setting
            ) {
                Setting::create($setting);
            }

        } finally {

            /*
            |--------------------------------------------------------------------------
            | Restore Previous Tenant Context
            |--------------------------------------------------------------------------
            */

            if ($previousTenant) {

                $this->tenantManager->setTenant(
                    $previousTenant
                );

            }
        }
    }

    protected function defaultSettings(
        Tenant $tenant
    ): array {

        $now = now();

        return [

            /*
            |--------------------------------------------------------------------------
            | Company
            |--------------------------------------------------------------------------
            */

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'country',
                'value' => 'IN',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'state',
                'value' => 'West Bengal',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'city',
                'value' => 'Kolkata',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'currency',
                'value' => 'INR',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'timezone',
                'value' => 'Asia/Kolkata',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'date_format',
                'value' => 'd-m-Y',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'time_format',
                'value' => '12h',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'week_start_day',
                'value' => 'sunday',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'fiscal_year_start_month',
                'value' => '4',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'fiscal_year_start_day',
                'value' => '1',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'decimal_places',
                'value' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'number_format',
                'value' => 'indian',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'currency_symbol',
                'value' => '₹',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            /*
            |--------------------------------------------------------------------------
            | Inventory
            |--------------------------------------------------------------------------
            */

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::INVENTORY->value,
                'key' => 'allow_negative_stock',
                'value' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::INVENTORY->value,
                'key' => 'costing_method',
                'value' => $this->defaultCostingMethod($tenant),
                'created_at' => $now,
                'updated_at' => $now,
            ],

            /*
            |--------------------------------------------------------------------------
            | Sales
            |--------------------------------------------------------------------------
            */

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::SALES->value,
                'key' => 'allow_below_cost_sale',
                'value' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::SALES->value,
                'key' => 'discount_before_tax',
                'value' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            /*
            |--------------------------------------------------------------------------
            | Purchase
            |--------------------------------------------------------------------------
            */

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::PURCHASE->value,
                'key' => 'require_grn',
                'value' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            /*
            |--------------------------------------------------------------------------
            | Accounting
            |--------------------------------------------------------------------------
            */

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::ACCOUNTING->value,
                'key' => 'auto_post_purchase',
                'value' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::ACCOUNTING->value,
                'key' => 'auto_post_sales',
                'value' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            /*
            |--------------------------------------------------------------------------
            | Tax
            |--------------------------------------------------------------------------
            */

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::TAX->value,
                'key' => 'gst_enabled',
                'value' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            [
                'tenant_id' => $tenant->id,
                'group' => SettingGroupEnum::TAX->value,
                'key' => 'default_tax_rate',
                'value' => 18,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }

    protected function defaultCostingMethod(
        Tenant $tenant
    ): string {

        return match ($tenant->business_type) {

            BusinessTypeEnum::MEDICINE,
            BusinessTypeEnum::MOBILE,
            BusinessTypeEnum::COMPUTER,
            BusinessTypeEnum::RETAIL
                => InventoryCostingMethodEnum::FIFO->value,

            default
                => InventoryCostingMethodEnum::WEIGHTED_AVERAGE->value,
        };
    }
}