<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;

use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Services\InventoryPostingService;

use App\Modules\Settings\Services\SettingService;
use App\Modules\Settings\Enums\SettingGroupEnum;

use App\Core\Exceptions\BusinessException;

class NegativeStockTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant =
            $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant(
                $this->tenant
            );

        $this->product =
            Product::create([
                'tenant_id' =>
                    $this->tenant->id,

                'name' =>
                    'Test Product',

                'code' =>
                    nextSystemNumber(
                        SystemNumberTypeEnum::PRODUCT
                    ),

                'sku' =>
                    'NEG-STOCK-001',

                'slug' =>
                    'neg-stock-' . random_int(
                        10000,
                        99999
                    ),

                'product_type' =>
                    ProductTypeEnum::PRODUCT,

                'inventory_tracking_type' =>
                    InventoryTrackingTypeEnum::NONE,

                'purchase_price' =>
                    100,

                'selling_price' =>
                    150,

                'is_active' =>
                    true,
            ]);

        $this->warehouse =
            Warehouse::create([
                'tenant_id' =>
                    $this->tenant->id,

                'name' =>
                    'Main Warehouse',

                'code' =>
                    nextSystemNumber(
                        SystemNumberTypeEnum::WAREHOUSE
                    ),

                'is_active' =>
                    true,
            ]);

        /*
        |--------------------------------------------------------------
        | Opening Stock
        |--------------------------------------------------------------
        */

        app(
            InventoryPostingService::class
        )->stockIn(
            new InventoryMovementData(
                productId:
                    $this->product->id,

                productVariantId:
                    null,

                warehouseId:
                    $this->warehouse->id,

                quantity:
                    10,

                unitCost:
                    100,

                transactionType:
                    'OPENING_STOCK',

                transactionDate:
                    now(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'OPEN-001',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Opening stock'
            )
        );
    }

    public function test_negative_stock_not_allowed(): void
    {
        app(SettingService::class)
            ->set(
                SettingGroupEnum::INVENTORY->value,
                'allow_negative_stock',
                false
            );

        $this->expectException(
            BusinessException::class
        );

        app(
            InventoryPostingService::class
        )->stockOut(
            new InventoryMovementData(
                productId:
                    $this->product->id,

                productVariantId:
                    null,

                warehouseId:
                    $this->warehouse->id,

                quantity:
                    20,

                unitCost:
                    0,

                transactionType:
                    'SALE',

                transactionDate:
                    now(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'SALE-001',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Negative stock test'
            )
        );
    }

    public function test_negative_stock_allowed(): void
    {
        app(SettingService::class)
            ->set(
                SettingGroupEnum::INVENTORY->value,
                'allow_negative_stock',
                true
            );

        $ledger =
            app(
                InventoryPostingService::class
            )->stockOut(
                new InventoryMovementData(
                    productId:
                        $this->product->id,

                    productVariantId:
                        null,

                    warehouseId:
                        $this->warehouse->id,

                    quantity:
                        20,

                    unitCost:
                        0,

                    transactionType:
                        'SALE',

                    transactionDate:
                        now(),

                    referenceType:
                        null,

                    referenceId:
                        null,

                    referenceNo:
                        'SALE-002',

                    batchId:
                        null,

                    serialId:
                        null,

                    remarks:
                        'Negative stock allowed'
                )
            );

        $this->assertEquals(
            -10,
            (float) $ledger->balance_quantity
        );
    }

    public function test_weighted_average_recovers_after_negative_stock(): void
    {
        app(SettingService::class)
            ->set(
                SettingGroupEnum::INVENTORY->value,
                'allow_negative_stock',
                true
            );

        $posting = app(
            InventoryPostingService::class
        );

        // Opening stock is 10 @ 100. Selling 20 creates -10 @ 100.
        $posting->stockOut(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 20,
                unitCost: 0,
                transactionType: 'SALE',
                transactionDate: now(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'RECOVERY-SALE-001',
                batchId: null,
                serialId: null,
                remarks: 'Create negative stock'
            )
        );

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertEquals(-10, (float) $stock->quantity);
        $this->assertEquals(100, (float) $stock->average_cost);

        // Receipt 5 @ 120 leaves -5; the existing average is preserved.
        $posting->stockIn(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 5,
                unitCost: 120,
                transactionType: 'PURCHASE',
                transactionDate: now(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'RECOVERY-PUR-001',
                batchId: null,
                serialId: null,
                remarks: 'Partial negative stock recovery'
            )
        );

        $stock->refresh();

        $this->assertEquals(-5, (float) $stock->quantity);
        $this->assertEquals(100, (float) $stock->average_cost);

        // Receipt 10 @ 120 clears the deficit and leaves 5 @ 140.
        $ledger = $posting->stockIn(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 10,
                unitCost: 120,
                transactionType: 'PURCHASE',
                transactionDate: now(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'RECOVERY-PUR-002',
                batchId: null,
                serialId: null,
                remarks: 'Complete negative stock recovery'
            )
        );

        $stock->refresh();

        $this->assertEquals(5, (float) $stock->quantity);

        $this->assertEqualsWithDelta(
            140,
            (float) $stock->average_cost,
            0.0001
        );

        $this->assertEquals(5, (float) $ledger->balance_quantity);

        $this->assertEqualsWithDelta(
            140,
            (float) $ledger->balance_average_cost,
            0.0001
        );
    }

}