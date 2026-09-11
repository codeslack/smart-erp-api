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

use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Services\InventoryPostingService;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Enums\SettingGroupEnum;
use App\Modules\Settings\Enums\InventoryCostingMethodEnum;

class WeightedAverageCostingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

    protected InventoryPostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Tenant
        |--------------------------------------------------------------------------
        */

        $this->tenant =
            $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant(
                $this->tenant
            );

        /*
        |--------------------------------------------------------------------------
        | Force Weighted Average
        |--------------------------------------------------------------------------
        */

        Setting::query()
            ->where(
                'tenant_id',
                $this->tenant->id
            )
            ->where(
                'group',
                SettingGroupEnum::INVENTORY->value
            )
            ->where(
                'key',
                'costing_method'
            )
            ->update([
                'value' =>
                    InventoryCostingMethodEnum::WEIGHTED_AVERAGE->value,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Product
        |--------------------------------------------------------------------------
        */

        $this->product = Product::create([
            'tenant_id' =>
                $this->tenant->id,

            'name' =>
                'Weighted Average Product',

            'code' =>
                nextSystemNumber(
                    SystemNumberTypeEnum::PRODUCT
                ),

            'sku' =>
                'WA-001',

            'slug' =>
                'wa-product-' .
                random_int(
                    10000,
                    99999
                ),

            'product_type' =>
                ProductTypeEnum::PRODUCT,

            'inventory_tracking_type' =>
                InventoryTrackingTypeEnum::NONE,

            'purchase_price' =>
                10,

            'selling_price' =>
                20,

            'is_active' =>
                true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Warehouse
        |--------------------------------------------------------------------------
        */

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

        $this->postingService =
            app(
                InventoryPostingService::class
            );
    }

    public function test_weighted_average_costing(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Receipt #1
        |--------------------------------------------------------------------------
        |
        | 100 @ 10
        |
        */

        $this->postingService->stockIn(
            new InventoryMovementData(

                productId:
                    $this->product->id,

                productVariantId:
                    null,

                warehouseId:
                    $this->warehouse->id,

                quantity:
                    100,

                unitCost:
                    10,

                transactionType:
                    'PURCHASE',

                transactionDate:
                    now()->subMinutes(2),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'PUR-001',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Receipt 1',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Receipt #2
        |--------------------------------------------------------------------------
        |
        | 50 @ 20
        |
        | Average:
        |
        | (100×10 + 50×20)
        | ----------------
        |      150
        |
        | = 13.3333
        |
        */

        $this->postingService->stockIn(
            new InventoryMovementData(

                productId:
                    $this->product->id,

                productVariantId:
                    null,

                warehouseId:
                    $this->warehouse->id,

                quantity:
                    50,

                unitCost:
                    20,

                transactionType:
                    'PURCHASE',

                transactionDate:
                    now()->subMinute(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'PUR-002',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Receipt 2',
            )
        );

        $stock = ProductStock::query()
            ->where(
                'product_id',
                $this->product->id
            )
            ->where(
                'warehouse_id',
                $this->warehouse->id
            )
            ->first();

        $this->assertNotNull(
            $stock
        );

        $this->assertEquals(
            150,
            (float) $stock->quantity
        );

        $this->assertEqualsWithDelta(
            13.3333,
            (float) $stock->average_cost,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | Stock Out
        |--------------------------------------------------------------------------
        |
        | Qty = 30
        |
        | Cost:
        |
        | 30 × 13.3333
        |
        */

        $ledger =
            $this->postingService->stockOut(
                new InventoryMovementData(

                    productId:
                        $this->product->id,

                    productVariantId:
                        null,

                    warehouseId:
                        $this->warehouse->id,

                    quantity:
                        30,

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
                        'SAL-001',

                    batchId:
                        null,

                    serialId:
                        null,

                    remarks:
                        'Weighted Average Sale',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Ledger Assertions
        |--------------------------------------------------------------------------
        */

        $this->assertInstanceOf(
            StockLedger::class,
            $ledger
        );

        $this->assertEquals(
            30,
            (float) $ledger->quantity_out
        );

        $this->assertEqualsWithDelta(
            13.3333,
            (float) $ledger->unit_cost,
            0.0001
        );

        $this->assertEqualsWithDelta(
            400,
            (float) $ledger->total_cost,
            0.1
        );

        /*
        |--------------------------------------------------------------------------
        | Product Stock
        |--------------------------------------------------------------------------
        */

        $stock->refresh();

        $this->assertEquals(
            120,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Average Cost Must Remain Same
        |--------------------------------------------------------------------------
        */

        $this->assertEqualsWithDelta(
            13.3333,
            (float) $stock->average_cost,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | Database Assertions
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'product_stocks',
            [
                'tenant_id' =>
                    $this->tenant->id,

                'product_id' =>
                    $this->product->id,

                'warehouse_id' =>
                    $this->warehouse->id,
            ]
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'tenant_id' =>
                    $this->tenant->id,

                'product_id' =>
                    $this->product->id,

                'warehouse_id' =>
                    $this->warehouse->id,

                'quantity_out' =>
                    30,
            ]
        );
    }
}