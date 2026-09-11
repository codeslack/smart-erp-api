<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;

use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Settings\Models\Setting;

use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\InventoryCostLayer;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Services\InventoryPostingService;

class InventoryTransferTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $sourceWarehouse;

    protected Warehouse $destinationWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        /*
        |--------------------------------------------------------------------------
        | FIFO Costing
        |--------------------------------------------------------------------------
        */

        Setting::updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'group'     => 'inventory',
                'key'       => 'costing_method',
            ],
            [
                'value'     => 'FIFO',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Product
        |--------------------------------------------------------------------------
        */

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,

            'name' => 'Transfer Product',

            'code' => 'PRD-000001',

            'sku' => 'TRANSFER-001',

            'slug' => 'transfer-product',

            'product_type' =>
                ProductTypeEnum::PRODUCT,

            'inventory_tracking_type' =>
                InventoryTrackingTypeEnum::NONE,

            'purchase_price' => 500,

            'selling_price' => 700,

            'is_active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Warehouses
        |--------------------------------------------------------------------------
        */

        $this->sourceWarehouse =
            Warehouse::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Main Warehouse',
                'code' => 'WH-MAIN',
            ]);

        $this->destinationWarehouse =
            Warehouse::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Branch Warehouse',
                'code' => 'WH-BRANCH',
            ]);
    }

    public function test_inventory_transfer_between_warehouses(): void
    {
        $postingService =
            app(InventoryPostingService::class);

        /*
        |--------------------------------------------------------------------------
        | Opening Stock
        |--------------------------------------------------------------------------
        */

        $postingService->stockIn(
            new InventoryMovementData(
                productId:
                    $this->product->id,

                productVariantId:
                    null,

                warehouseId:
                    $this->sourceWarehouse->id,

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
                    'OPEN-001',

                batchId:
                    null,

                serialId:
                    null,

                remarks:
                    'Opening stock'
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Transfer Out
        |--------------------------------------------------------------------------
        */

        $outLedger =
            $postingService->stockOut(
                new InventoryMovementData(
                    productId:
                        $this->product->id,

                    productVariantId:
                        null,

                    warehouseId:
                        $this->sourceWarehouse->id,

                    quantity:
                        40,

                    unitCost:
                        0,

                    transactionType:
                        'TRANSFER_OUT',

                    transactionDate:
                        now(),

                    referenceType:
                        null,

                    referenceId:
                        null,

                    referenceNo:
                        'TRF-001',

                    batchId:
                        null,

                    serialId:
                        null,

                    remarks:
                        'Transfer out'
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Transfer In
        |--------------------------------------------------------------------------
        */

        $inLedger =
            $postingService->stockIn(
                new InventoryMovementData(
                    productId:
                        $this->product->id,

                    productVariantId:
                        null,

                    warehouseId:
                        $this->destinationWarehouse->id,

                    quantity:
                        40,

                    unitCost:
                        (float) $outLedger->unit_cost,

                    transactionType:
                        'TRANSFER_IN',

                    transactionDate:
                        now(),

                    referenceType:
                        null,

                    referenceId:
                        null,

                    referenceNo:
                        'TRF-001',

                    batchId:
                        null,

                    serialId:
                        null,

                    remarks:
                        'Transfer in'
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Source Stock
        |--------------------------------------------------------------------------
        */

        $sourceStock =
            ProductStock::query()
                ->where(
                    'product_id',
                    $this->product->id
                )
                ->where(
                    'warehouse_id',
                    $this->sourceWarehouse->id
                )
                ->first();

        $this->assertNotNull(
            $sourceStock
        );

        $this->assertEquals(
            60,
            (float) $sourceStock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Destination Stock
        |--------------------------------------------------------------------------
        */

        $destinationStock =
            ProductStock::query()
                ->where(
                    'product_id',
                    $this->product->id
                )
                ->where(
                    'warehouse_id',
                    $this->destinationWarehouse->id
                )
                ->first();

        $this->assertNotNull(
            $destinationStock
        );

        $this->assertEquals(
            40,
            (float) $destinationStock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Transfer Out Ledger
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            40,
            (float) $outLedger->quantity_out
        );

        /*
        |--------------------------------------------------------------------------
        | Transfer In Ledger
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            40,
            (float) $inLedger->quantity_in
        );

        /*
        |--------------------------------------------------------------------------
        | FIFO Layer Created
        |--------------------------------------------------------------------------
        */

        $layer =
            InventoryCostLayer::query()
                ->where(
                    'product_id',
                    $this->product->id
                )
                ->where(
                    'warehouse_id',
                    $this->destinationWarehouse->id
                )
                ->first();

        $this->assertNotNull(
            $layer
        );

        $this->assertEquals(
            40,
            (float) $layer->original_quantity
        );

        $this->assertEquals(
            40,
            (float) $layer->remaining_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Database Assertions
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'warehouse_id' =>
                    $this->sourceWarehouse->id,

                'transaction_type' =>
                    'TRANSFER_OUT',
            ]
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'warehouse_id' =>
                    $this->destinationWarehouse->id,

                'transaction_type' =>
                    'TRANSFER_IN',
            ]
        );
    }
}
