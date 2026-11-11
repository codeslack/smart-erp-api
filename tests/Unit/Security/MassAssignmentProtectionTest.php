<?php

namespace Tests\Unit\Security;

use Tests\TestCase;

use App\Modules\User\Models\User;
use App\Modules\Rbac\Models\Permission;
use App\Modules\Settings\Models\Setting;
use App\Modules\SystemNumber\Models\SystemNumber;
use App\Modules\Product\Models\ProductVariantAttribute;
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryCostLayerConsumption;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

class MassAssignmentProtectionTest extends TestCase
{
    public function test_models_allow_only_explicitly_fillable_attributes(): void
    {
        $models = [
            [new User(), 'name'],
            [new Permission(), 'name'],
            [new Setting(), 'group'],
            [new SystemNumber(), 'type'],
            [new ProductVariantAttribute(), 'attribute_name'],
            [new InventoryCostLayer(), 'stock_ledger_id'],
            [new InventoryCostLayerConsumption(), 'stock_ledger_id'],
            [new ProductBatch(), 'batch_no'],
            [new ProductSerial(), 'serial_number'],
            [new ProductStock(), 'quantity'],
            [new StockLedger(), 'transaction_type'],
        ];

        foreach ($models as [$model, $allowedAttribute]) {
            $this->assertTrue(
                $model->isFillable($allowedAttribute),
                $model::class . " must allow {$allowedAttribute}."
            );

            $this->assertFalse(
                $model->isFillable('unexpected_mass_assignment_attribute'),
                $model::class . ' must reject attributes outside its allowlist.'
            );

            $this->assertFalse(
                $model->isFillable('id'),
                $model::class . ' must not allow mass assignment of its primary key.'
            );
        }
    }

    public function test_user_identity_and_authentication_metadata_are_not_mass_assignable(): void
    {
        $user = new User();

        $this->assertTrue($user->isFillable('password'));
        $this->assertFalse($user->isFillable('tenant_id'));
        $this->assertFalse($user->isFillable('uuid'));
        $this->assertFalse($user->isFillable('last_login_at'));
        $this->assertFalse($user->isFillable('email_verified_at'));
    }

    public function test_tenant_owned_inventory_models_do_not_allow_tenant_id_mass_assignment(): void
    {
        foreach ([
            new InventoryCostLayer(),
            new InventoryCostLayerConsumption(),
            new ProductBatch(),
            new ProductSerial(),
            new ProductStock(),
            new StockLedger(),
            new Setting(),
        ] as $model) {
            $this->assertFalse(
                $model->isFillable('tenant_id'),
                $model::class . ' must not allow tenant_id mass assignment.'
            );
        }
    }
}
