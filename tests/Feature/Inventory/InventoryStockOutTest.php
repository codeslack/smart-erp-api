<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;

use App\Core\Tenant\TenantManager;

use App\Modules\Settings\Models\Setting;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Models\InventoryCostLayer;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Services\InventoryPostingService;

class InventoryStockOutTest extends TestCase
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

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        Setting::updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'group' => 'inventory',
                'key' => 'costing_method',
            ],
            [
                'value' => 'FIFO',
            ]
        );

        logger()->info(setting('inventory.costing_method'));

        /*
        |--------------------------------------------------------------------------
        | Product
        |--------------------------------------------------------------------------
        */

        $this->product = Product::create([
            'tenant_id' =>
                $this->tenant->id,

            'name' =>
                'Test Computer Mouse',

            'code' =>
                nextSystemNumber(
                    SystemNumberTypeEnum::PRODUCT
                ),

            'sku' =>
                'TEST-MOUSE-' . random_int(100000, 999999),

            'slug' =>
                'test-mouse-' . random_int(100000, 999999),

            'product_type' =>
                ProductTypeEnum::PRODUCT,

            'inventory_tracking_type' =>
                InventoryTrackingTypeEnum::NONE,

            'purchase_price' =>
                500,

            'selling_price' =>
                700,

            'is_active' =>
                true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Warehouse
        |--------------------------------------------------------------------------
        */

        $this->warehouse = Warehouse::create([
            'tenant_id' =>
                $this->tenant->id,

            'name' => 'Main Warehouse', 

            'code' =>
                nextSystemNumber(
                    SystemNumberTypeEnum::WAREHOUSE
                ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Service
        |--------------------------------------------------------------------------
        */

        $this->postingService =
            app(
                InventoryPostingService::class
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Stock OUT consumes FIFO layers
    |--------------------------------------------------------------------------
    */

    public function test_stock_out_consumes_fifo_layers_in_fifo_order(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange - First Stock IN
        |--------------------------------------------------------------------------
        |
        | 100 units @ 500
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
                    500,

                transactionType:
                    'OPENING_STOCK',

                transactionDate:
                    now()->subMinutes(2),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'IN-TEST-001',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'First FIFO layer',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Arrange - Second Stock IN
        |--------------------------------------------------------------------------
        |
        | 50 units @ 600
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
                    600,

                transactionType:
                    'PURCHASE',

                transactionDate:
                    now()->subMinute(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'IN-TEST-002',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Second FIFO layer',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Initial Stock
        |--------------------------------------------------------------------------
        */

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

        $this->assertNotNull($stock);

        $this->assertEquals(
            150,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Verify FIFO Layers Before OUT
        |--------------------------------------------------------------------------
        */

        $layers = InventoryCostLayer::query()
            ->where(
                'product_id',
                $this->product->id
            )
            ->where(
                'warehouse_id',
                $this->warehouse->id
            )
            ->orderBy('layer_date')
            ->orderBy('id')
            ->get();

        $this->assertCount(
            2,
            $layers
        );

        $this->assertEquals(
            100,
            (float) $layers[0]->remaining_quantity
        );

        $this->assertEquals(
            500,
            (float) $layers[0]->unit_cost
        );

        $this->assertEquals(
            50,
            (float) $layers[1]->remaining_quantity
        );

        $this->assertEquals(
            600,
            (float) $layers[1]->unit_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Stock OUT
        |--------------------------------------------------------------------------
        |
        | Request:
        |
        | 120 units
        |
        | FIFO:
        |
        | 100 @ 500 = 50,000
        | 20  @ 600 = 12,000
        |
        | Total = 62,000
        |
        */

        $ledger = $this->postingService->stockOut(
            new InventoryMovementData(

                productId:
                    $this->product->id,

                productVariantId:
                    null,

                warehouseId:
                    $this->warehouse->id,

                quantity:
                    120,

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
                    'OUT-TEST-001',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'FIFO stock out test',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Stock Ledger
        |--------------------------------------------------------------------------
        */

        $this->assertInstanceOf(
            StockLedger::class,
            $ledger
        );

        $this->assertEquals(
            0,
            (float) $ledger->quantity_in
        );

        $this->assertEquals(
            120,
            (float) $ledger->quantity_out
        );

        $this->assertEquals(
            62000,
            (float) $ledger->total_cost
        );

        $this->assertEquals(
            516.6667,
            round(
                (float) $ledger->unit_cost,
                4
            )
        );

        $this->assertEquals(
            30,
            (float) $ledger->balance_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Product Stock
        |--------------------------------------------------------------------------
        */

        $stock->refresh();

        $this->assertEquals(
            30,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - First FIFO Layer
        |--------------------------------------------------------------------------
        |
        | Completely consumed.
        |
        */

        $firstLayer = $layers[0]->fresh();

        $this->assertEquals(
            0,
            (float) $firstLayer->remaining_quantity
        );

        $this->assertEquals(
            'EXHAUSTED',
            $firstLayer->status->value
                ?? $firstLayer->status
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Second FIFO Layer
        |--------------------------------------------------------------------------
        |
        | Started:
        |
        | 50 @ 600
        |
        | Consumed:
        |
        | 20
        |
        | Remaining:
        |
        | 30 @ 600
        |
        */

        $secondLayer = $layers[1]->fresh();

        $this->assertEquals(
            30,
            (float) $secondLayer->remaining_quantity
        );

        $this->assertEquals(
            600,
            (float) $secondLayer->unit_cost
        );

        $this->assertEquals(
            'OPEN',
            $secondLayer->status->value
                ?? $secondLayer->status
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Database
        |--------------------------------------------------------------------------
        */

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
                    120,

                'total_cost' =>
                    62000,
            ]
        );

        $this->assertDatabaseHas(
            'inventory_cost_layers',
            [
                'tenant_id' =>
                    $this->tenant->id,

                'product_id' =>
                    $this->product->id,

                'warehouse_id' =>
                    $this->warehouse->id,

                'remaining_quantity' =>
                    0,

                'status' =>
                    'EXHAUSTED',
            ]
        );

        $this->assertDatabaseHas(
            'inventory_cost_layers',
            [
                'tenant_id' =>
                    $this->tenant->id,

                'product_id' =>
                    $this->product->id,

                'warehouse_id' =>
                    $this->warehouse->id,

                'remaining_quantity' =>
                    30,

                'unit_cost' =>
                    600,

                'status' =>
                    'OPEN',
            ]
        );
    }
}