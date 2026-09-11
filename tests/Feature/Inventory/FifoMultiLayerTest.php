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
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryCostLayerConsumption;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Services\InventoryPostingService;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Enums\SettingGroupEnum;
use App\Modules\Settings\Enums\InventoryCostingMethodEnum;
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

class FifoMultiLayerTest extends TestCase
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
        | Force FIFO
        |--------------------------------------------------------------------------
        */
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

        logger()->info("fifoMultiTest: ", [
            setting('inventory.costing_method')
        ]);

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
                    InventoryCostingMethodEnum::FIFO->value,
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
                'FIFO Test Product',

            'code' =>
                nextSystemNumber(
                    SystemNumberTypeEnum::PRODUCT
                ),

            'sku' =>
                'FIFO-001',

            'slug' =>
                'fifo-product-' .
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

    public function test_fifo_consumes_multiple_layers_in_correct_order(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Layer #1
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
                    now()->subDays(2),

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
                    'Layer 1'
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Layer #2
        |--------------------------------------------------------------------------
        |
        | 50 @ 20
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
                    now()->subDay(),

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
                    'Layer 2'
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Sale
        |--------------------------------------------------------------------------
        |
        | Sell 120
        |
        | Expected:
        | 100 @ 10 = 1000
        | 20 @ 20  = 400
        |
        | Total = 1400
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
                        'SAL-001',

                    batchId:
                        null,

                    serialId:
                        null,

                    remarks:
                        'FIFO Sale'
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Ledger
        |--------------------------------------------------------------------------
        */

        $this->assertInstanceOf(
            StockLedger::class,
            $ledger
        );

        $this->assertEquals(
            120,
            (float) $ledger->quantity_out
        );

        $this->assertEquals(
            1400,
            (float) $ledger->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | FIFO Layers
        |--------------------------------------------------------------------------
        */

        $layers =
            InventoryCostLayer::query()
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

        $layer1 =
            $layers->first();

        $layer2 =
            $layers->last();

        /*
        |--------------------------------------------------------------------------
        | Layer #1
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            0,
            (float) $layer1->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::EXHAUSTED,
            $layer1->status
        );

        /*
        |--------------------------------------------------------------------------
        | Layer #2
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            30,
            (float) $layer2->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layer2->status
        );

        /*
        |--------------------------------------------------------------------------
        | Product Stock
        |--------------------------------------------------------------------------
        */

        $stock =
            ProductStock::query()
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
            30,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Database Assertions
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'inventory_cost_layers',
            [
                'id' =>
                    $layer1->id,

                'remaining_quantity' =>
                    0,
            ]
        );

        $this->assertDatabaseHas(
            'inventory_cost_layers',
            [
                'id' =>
                    $layer2->id,

                'remaining_quantity' =>
                    30,
            ]
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'tenant_id' =>
                    $this->tenant->id,

                'product_id' =>
                    $this->product->id,

                'quantity_out' =>
                    120,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | FIFO Consumption History
        |--------------------------------------------------------------------------
        |
        | Sale consumed:
        |
        | Layer #1 → 100 @ 10 = 1000
        | Layer #2 → 20  @ 20 =  400
        |
        | Total = 1400
        |
        */

        $consumptions =
            InventoryCostLayerConsumption::query()
                ->where(
                    'stock_ledger_id',
                    $ledger->id
                )
                ->orderBy('id')
                ->get();

        $this->assertCount(
            2,
            $consumptions
        );

        $consumption1 =
            $consumptions->first();

        $consumption2 =
            $consumptions->last();

        /*
        |--------------------------------------------------------------------------
        | Consumption #1
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            $layer1->id,
            $consumption1->inventory_cost_layer_id
        );

        $this->assertEquals(
            $ledger->id,
            $consumption1->stock_ledger_id
        );

        $this->assertEquals(
            100,
            (float) $consumption1->quantity
        );

        $this->assertEquals(
            10,
            (float) $consumption1->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $consumption1->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Consumption #2
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            $layer2->id,
            $consumption2->inventory_cost_layer_id
        );

        $this->assertEquals(
            $ledger->id,
            $consumption2->stock_ledger_id
        );

        $this->assertEquals(
            20,
            (float) $consumption2->quantity
        );

        $this->assertEquals(
            20,
            (float) $consumption2->unit_cost
        );

        $this->assertEquals(
            400,
            (float) $consumption2->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Total Consumption Cost
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1400,
            (float) $consumptions->sum('total_cost')
        );

        /*
        |--------------------------------------------------------------------------
        | Database Assertions
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'inventory_cost_layer_consumptions',
            [
                'tenant_id' =>
                    $this->tenant->id,

                'inventory_cost_layer_id' =>
                    $layer1->id,

                'stock_ledger_id' =>
                    $ledger->id,

                'quantity' =>
                    100,

                'unit_cost' =>
                    10,

                'total_cost' =>
                    1000,
            ]
        );

        $this->assertDatabaseHas(
            'inventory_cost_layer_consumptions',
            [
                'tenant_id' =>
                    $this->tenant->id,

                'inventory_cost_layer_id' =>
                    $layer2->id,

                'stock_ledger_id' =>
                    $ledger->id,

                'quantity' =>
                    20,

                'unit_cost' =>
                    20,

                'total_cost' =>
                    400,
            ]
        );
    }
}
