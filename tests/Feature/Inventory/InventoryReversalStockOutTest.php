<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;

use App\Core\Exceptions\BusinessException;
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
use App\Modules\Inventory\Models\InventoryCostLayerConsumption;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryReversalData;

use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;
use App\Modules\Warehouse\Services\WarehouseService;

class InventoryReversalStockOutTest extends TestCase
{
    use RefreshDatabase;

    use CreatesTenant;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

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
            ->setTenant($this->tenant);

        /*
        |--------------------------------------------------------------------------
        | Inventory Settings
        |--------------------------------------------------------------------------
        */

        Setting::updateOrCreate(
            [
                'tenant_id' =>
                    $this->tenant->id,

                'group' =>
                    'inventory',

                'key' =>
                    'costing_method',
            ],
            [
                'value' =>
                    'FIFO',
            ]
        );

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
                'TEST-MOUSE-REV-OUT-001',

            'slug' =>
                'TEST-MOUSE-REV-OUT-001-' .
                random_int(100000, 999999),

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
            app(WarehouseService::class)->create([
                'name' =>
                    'Main Warehouse',
            ]);
    }

    public function test_stock_out_can_be_reversed(): void
    {
        $posting =
            app(InventoryPostingService::class);

        /*
        |--------------------------------------------------------------------------
        | Stock IN - Layer 1
        |--------------------------------------------------------------------------
        */

        $posting->stockIn(
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
                    'PUR-REV-001',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'FIFO layer 1',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Stock IN - Layer 2
        |--------------------------------------------------------------------------
        */

        $posting->stockIn(
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
                    'PUR-REV-002',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'FIFO layer 2',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Stock OUT
        |--------------------------------------------------------------------------
        |
        | FIFO:
        |
        | 100 x 10 = 1000
        | 20  x 20 = 400
        |
        | Total = 1400
        |
        */

        $saleLedger =
            $posting->stockOut(
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
                        'SAL-REV-001',

                    batchId:
                        null,

                    serialId:
                        null,

                    remarks:
                        'FIFO stock out reversal test',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Verify Stock OUT
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            120,
            (float) $saleLedger->quantity_out
        );

        $this->assertEquals(
            1400,
            (float) $saleLedger->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Product Stock Before Reversal
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

        $this->assertNotNull($stock);

        $this->assertEquals(
            30,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Verify FIFO Layers Before Reversal
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

        /*
         * Layer 1 fully consumed.
         */

        $this->assertEquals(
            0,
            (float) $layers[0]->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::EXHAUSTED,
            $layers[0]->status
        );

        /*
         * Layer 2 has 30 remaining.
         */

        $this->assertEquals(
            30,
            (float) $layers[1]->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layers[1]->status
        );

        /*
        |--------------------------------------------------------------------------
        | Verify FIFO Consumption History
        |--------------------------------------------------------------------------
        */

        $consumptions =
            InventoryCostLayerConsumption::query()
                ->where(
                    'stock_ledger_id',
                    $saleLedger->id
                )
                ->orderBy('id')
                ->get();

        $this->assertCount(
            2,
            $consumptions
        );

        /*
         * Layer 1 consumption.
         */

        $this->assertEquals(
            100,
            (float) $consumptions[0]->quantity
        );

        $this->assertEquals(
            10,
            (float) $consumptions[0]->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $consumptions[0]->total_cost
        );

        /*
         * Layer 2 consumption.
         */

        $this->assertEquals(
            20,
            (float) $consumptions[1]->quantity
        );

        $this->assertEquals(
            20,
            (float) $consumptions[1]->unit_cost
        );

        $this->assertEquals(
            400,
            (float) $consumptions[1]->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Reverse Stock OUT
        |--------------------------------------------------------------------------
        */

        $reversalLedger =
            app(
                InventoryReversalService::class
            )->reverse(
                new InventoryReversalData(
                    stockLedgerId:
                        $saleLedger->id,

                    reversalDate:
                        now(),

                    referenceType:
                        null,

                    referenceId:
                        null,

                    referenceNo:
                        'REV-SAL-001',

                    remarks:
                        'Stock out reversal test',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Assert Reversal Ledger
        |--------------------------------------------------------------------------
        */

        $this->assertInstanceOf(
            StockLedger::class,
            $reversalLedger
        );

        $this->assertEquals(
            InventoryTransactionTypeEnum::REVERSAL_IN,
            $reversalLedger->transaction_type
        );

        $this->assertEquals(
            $saleLedger->id,
            $reversalLedger->reversal_of_ledger_id
        );

        $this->assertEquals(
            120,
            (float) $reversalLedger->quantity_in
        );

        $this->assertEquals(
            0,
            (float) $reversalLedger->quantity_out
        );

        $this->assertEquals(
            1400,
            (float) $reversalLedger->total_cost
        );

        $this->assertEquals(
            150,
            (float) $reversalLedger->balance_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Assert Product Stock Restored
        |--------------------------------------------------------------------------
        */

        $stock->refresh();

        $this->assertEquals(
            150,
            (float) $stock->quantity
        );

        /*
         * FIFO posting keeps the ProductStock average cost.
         */

        $this->assertEqualsWithDelta(
            13.3333,
            (float) $stock->average_cost,
            0.0001
        );

        /*
        |--------------------------------------------------------------------------
        | Assert FIFO Layers Restored
        |--------------------------------------------------------------------------
        */

        $layers->each->refresh();

        /*
         * Layer 1 restored.
         */

        $this->assertEquals(
            100,
            (float) $layers[0]->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layers[0]->status
        );

        /*
         * Layer 2 restored.
         */

        $this->assertEquals(
            50,
            (float) $layers[1]->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layers[1]->status
        );

        /*
        |--------------------------------------------------------------------------
        | Consumption History Must Remain
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount(
            'inventory_cost_layer_consumptions',
            2
        );

        /*
        |--------------------------------------------------------------------------
        | Reversal Database Assertions
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'id' =>
                    $reversalLedger->id,

                'tenant_id' =>
                    $this->tenant->id,

                'product_id' =>
                    $this->product->id,

                'warehouse_id' =>
                    $this->warehouse->id,

                'transaction_type' =>
                    InventoryTransactionTypeEnum::REVERSAL_IN->value,

                'reversal_of_ledger_id' =>
                    $saleLedger->id,

                'quantity_in' =>
                    120,

                'quantity_out' =>
                    0,

                'total_cost' =>
                    1400,
            ]
        );
    }

    public function test_already_reversed_stock_out_cannot_be_reversed_again(): void
    {
        $posting =
            app(InventoryPostingService::class);

        /*
        |--------------------------------------------------------------------------
        | Stock IN
        |--------------------------------------------------------------------------
        */

        $posting->stockIn(
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
                    now(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'PUR-REV-003',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Duplicate reversal test',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Stock OUT
        |--------------------------------------------------------------------------
        */

        $saleLedger =
            $posting->stockOut(
                new InventoryMovementData(
                    productId:
                        $this->product->id,

                    productVariantId:
                        null,

                    warehouseId:
                        $this->warehouse->id,

                    quantity:
                        40,

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
                        'SAL-REV-002',

                    batchId:
                        null,

                    serialId:
                        null,

                    remarks:
                        'Duplicate reversal test',
                )
            );

        $service =
            app(
                InventoryReversalService::class
            );

        /*
        |--------------------------------------------------------------------------
        | First Reversal
        |--------------------------------------------------------------------------
        */

        $service->reverse(
            new InventoryReversalData(
                stockLedgerId:
                    $saleLedger->id,

                reversalDate:
                    now(),

                referenceNo:
                    'REV-SAL-002',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Second Reversal
        |--------------------------------------------------------------------------
        */

        try {
            $service->reverse(
                new InventoryReversalData(
                    stockLedgerId:
                        $saleLedger->id,

                    reversalDate:
                        now(),

                    referenceNo:
                        'REV-SAL-003',
                )
            );

            $this->fail(
                'Expected BusinessException was not thrown.'
            );
        } catch (BusinessException $e) {
            $this->assertSame(
                'LEDGER_ALREADY_REVERSED',
                $e->errorCode()
            );

            $this->assertSame(
                'Ledger already reversed.',
                $e->getMessage()
            );
        }
    }
}