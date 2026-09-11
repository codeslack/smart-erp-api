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

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryReversalData;

use App\Modules\Inventory\Enums\StockTransactionTypeEnum;
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;
use App\Modules\Warehouse\Services\WarehouseService;

class InventoryReversalStockInTest extends TestCase
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
                'TEST-MOUSE-REV-001',

            'slug' =>
                'TEST-MOUSE-REV-001-' .
                random_int(100000, 999999),

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

        $this->warehouse =
            app(WarehouseService::class)->create([
                'name' =>
                    'Main Warehouse',
            ]);
    }

    public function test_stock_in_can_be_reversed(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange - Stock IN
        |--------------------------------------------------------------------------
        */

        $movement =
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
                    now(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'OPEN-REV-001',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Opening stock for reversal test',
            );

        /*
        |--------------------------------------------------------------------------
        | Act - Stock IN
        |--------------------------------------------------------------------------
        */

        $originalLedger =
            app(
                InventoryPostingService::class
            )->stockIn(
                $movement
            );

        /*
        |--------------------------------------------------------------------------
        | Verify Original Stock
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
            100,
            (float) $stock->quantity
        );

        $this->assertEquals(
            500,
            (float) $stock->average_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Original FIFO Layer
        |--------------------------------------------------------------------------
        */

        $layer =
            InventoryCostLayer::query()
                ->where(
                    'stock_ledger_id',
                    $originalLedger->id
                )
                ->first();

        $this->assertNotNull($layer);

        $this->assertEquals(
            100,
            (float) $layer->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layer->status
        );

        /*
        |--------------------------------------------------------------------------
        | Arrange - Reversal
        |--------------------------------------------------------------------------
        */

        $reversalData =
            new InventoryReversalData(
                stockLedgerId:
                    $originalLedger->id,

                reversalDate:
                    now(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'REV-OPEN-001',

                remarks:
                    'Opening stock reversal test',
            );

        /*
        |--------------------------------------------------------------------------
        | Act - Reverse
        |--------------------------------------------------------------------------
        */

        $reversalLedger =
            app(
                InventoryReversalService::class
            )->reverse(
                $reversalData
            );

        /*
        |--------------------------------------------------------------------------
        | Assert - Reversal Ledger
        |--------------------------------------------------------------------------
        */

        $this->assertInstanceOf(
            StockLedger::class,
            $reversalLedger
        );

        $this->assertEquals(
            StockTransactionTypeEnum::REVERSAL_OUT,
            $reversalLedger->transaction_type
        );

        $this->assertEquals(
            $originalLedger->id,
            $reversalLedger->reversal_of_ledger_id
        );

        $this->assertEquals(
            0,
            (float) $reversalLedger->quantity_in
        );

        $this->assertEquals(
            100,
            (float) $reversalLedger->quantity_out
        );

        $this->assertEquals(
            500,
            (float) $reversalLedger->unit_cost
        );

        $this->assertEquals(
            50000,
            (float) $reversalLedger->total_cost
        );

        $this->assertEquals(
            0,
            (float) $reversalLedger->balance_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Product Stock
        |--------------------------------------------------------------------------
        */

        $stock->refresh();

        $this->assertEquals(
            0,
            (float) $stock->quantity
        );

        $this->assertEquals(
            0,
            (float) $stock->average_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - FIFO Layer
        |--------------------------------------------------------------------------
        */

        $layer->refresh();

        $this->assertEquals(
            100,
            (float) $layer->original_quantity
        );

        $this->assertEquals(
            0,
            (float) $layer->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::REVERSED,
            $layer->status
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Reversal Relationship
        |--------------------------------------------------------------------------
        */

        $this->assertTrue(
            $originalLedger
                ->fresh()
                ->reversal()
                ->whereKey($reversalLedger->id)
                ->exists()
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Database
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
                    StockTransactionTypeEnum::REVERSAL_OUT->value,

                'reversal_of_ledger_id' =>
                    $originalLedger->id,

                'quantity_out' =>
                    100,

                'total_cost' =>
                    50000,
            ]
        );

        $this->assertDatabaseHas(
            'inventory_cost_layers',
            [
                'id' =>
                    $layer->id,

                'status' =>
                    InventoryCostLayerStatusEnum::REVERSED->value,

                'remaining_quantity' =>
                    0,
            ]
        );
    }

    public function test_already_reversed_stock_in_cannot_be_reversed_again(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange
        |--------------------------------------------------------------------------
        */

        $movement =
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
                    now(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'OPEN-REV-002',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Duplicate reversal test',
            );

        $originalLedger =
            app(
                InventoryPostingService::class
            )->stockIn(
                $movement
            );

        $reversalData =
            new InventoryReversalData(
                stockLedgerId:
                    $originalLedger->id,

                reversalDate:
                    now(),

                referenceNo:
                    'REV-OPEN-002',
            );

        /*
        |--------------------------------------------------------------------------
        | First Reversal
        |--------------------------------------------------------------------------
        */

        app(
            InventoryReversalService::class
        )->reverse(
            $reversalData
        );

        /*
        |--------------------------------------------------------------------------
        | Second Reversal
        |--------------------------------------------------------------------------
        */

        $this->expectException(
            BusinessException::class
        );

        $this->expectExceptionMessage(
            'Ledger already reversed.'
        );

        // $this->expectExceptionCode(
        //     'LEDGER_ALREADY_REVERSED'
        // );

        app(
            InventoryReversalService::class
        )->reverse(
            $reversalData
        );
    }
}