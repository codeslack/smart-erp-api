<?php

namespace App\Modules\Settings\Database\Seeders;

use App\Modules\Settings\Enums\InventoryCostingMethodEnum;
use App\Modules\Settings\Enums\SettingGroupEnum;
use App\Modules\Settings\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [

            // Company
            [
                'group' => 'company',
                'key' => 'country',
                'value' => 'IN',
            ],

            [
                'group' => 'company',
                'key' => 'state',
                'value' => 'West Bengal',
            ],

            [
                'group' => 'company',
                'key' => 'city',
                'value' => 'Kolkata',
            ],

            [
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'currency',
                'value' => 'INR',
            ],

            [
                'group' => SettingGroupEnum::COMPANY->value,
                'key' => 'timezone',
                'value' => 'Asia/Kolkata',
            ],

            [
                'group' => 'company',
                'key' => 'decimal_places',
                'value' => 2,
            ],

            // Inventory
            [
                'group' => SettingGroupEnum::INVENTORY->value,
                'key' => 'allow_negative_stock',
                'value' => false,
            ],

            [
                'group' => SettingGroupEnum::INVENTORY->value,
                'key' => 'costing_method',
                'value' => InventoryCostingMethodEnum::WEIGHTED_AVERAGE->value,
            ],

            [
                'group' => 'inventory',
                'key' => 'auto_generate_batch',
                'value' => true,
            ],

            [
                'group' => 'inventory',
                'key' => 'auto_generate_serial',
                'value' => false,
            ],            
        ];

        foreach ($settings as $setting) {

            Setting::query()->updateOrCreate(
                [
                    'tenant_id' => tenantId(),
                    'group' => $setting['group'],
                    'key' => $setting['key'],
                ],
                [
                    'value' => $setting['value'],
                ]
            );
        }
    }
}