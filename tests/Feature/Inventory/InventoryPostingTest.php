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
use App\Modules\Warehouse\Services\WarehouseService;

class InventoryPostingTest extends TestCase
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
                'TEST-MOUSE-001',

            'slug' =>
                'TEST-MOUSE-001' . random_int(100000, 999999),

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

        $this->warehouse = app(WarehouseService::class)->create([
            'name' => 'Main Warehouse',
        ]);

    }

    public function test_stock_in_creates_product_stock_ledger_and_fifo_layer(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Arrange
        |--------------------------------------------------------------------------
        */

        $movement = new InventoryMovementData(

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
                'OPEN-TEST-001',

            batchId:
                null,

            serialId:
                null,

            remarks:
                'Inventory opening stock test',
        );

        /*
        |--------------------------------------------------------------------------
        | Act
        |--------------------------------------------------------------------------
        */

        $ledger = app(
            InventoryPostingService::class
        )->stockIn(
            $movement
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Ledger
        |--------------------------------------------------------------------------
        */

        $this->assertInstanceOf(
            StockLedger::class,
            $ledger
        );

        $this->assertEquals(
            100,
            (float) $ledger->quantity_in
        );

        $this->assertEquals(
            0,
            (float) $ledger->quantity_out
        );

        $this->assertEquals(
            500,
            (float) $ledger->unit_cost
        );

        $this->assertEquals(
            50000,
            (float) $ledger->total_cost
        );

        $this->assertEquals(
            100,
            (float) $ledger->balance_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Product Stock
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
            100,
            (float) $stock->quantity
        );

        $this->assertEquals(
            500,
            (float) $stock->average_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - FIFO Cost Layer
        |--------------------------------------------------------------------------
        */

        $layer = InventoryCostLayer::query()
            ->where(
                'product_id',
                $this->product->id
            )
            ->where(
                'warehouse_id',
                $this->warehouse->id
            )
            ->first();

        $this->assertNotNull($layer);

        $this->assertEquals(
            100,
            (float) $layer->original_quantity
        );

        $this->assertEquals(
            100,
            (float) $layer->remaining_quantity
        );

        $this->assertEquals(
            500,
            (float) $layer->unit_cost
        );

        $this->assertEquals(
            'OPEN',
            $layer->status->value
                ?? $layer->status
        );

        /*
        |--------------------------------------------------------------------------
        | Assert - Database
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

                'quantity_in' =>
                    100,
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

                'original_quantity' =>
                    100,

                'remaining_quantity' =>
                    100,
            ]
        );
    }
}