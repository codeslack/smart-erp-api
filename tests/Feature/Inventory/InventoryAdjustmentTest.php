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

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Enums\StockTransactionTypeEnum;

class InventoryAdjustmentTest extends TestCase
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

        $this->product = Product::create([
            'tenant_id' =>
                $this->tenant->id,

            'name' =>
                'Adjustment Product',

            'code' =>
                nextSystemNumber(
                    SystemNumberTypeEnum::PRODUCT
                ),

            'sku' =>
                'ADJ-001',

            'slug' =>
                'adj-001-' . random_int(1000, 9999),

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
    }

    public function test_stock_adjustment_in(): void
    {
        $service =
            app(
                InventoryPostingService::class
            );

        /*
        |--------------------------------------------------------------------------
        | Opening Stock
        |--------------------------------------------------------------------------
        */

        $service->stockIn(
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
                    StockTransactionTypeEnum::OPENING_STOCK->value,

                transactionDate:
                    now(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'OPEN-001'
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Adjustment In
        |--------------------------------------------------------------------------
        */

        $ledger =
            $service->stockIn(
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
                        10,

                    transactionType:
                        StockTransactionTypeEnum::ADJUSTMENT_IN->value,

                    transactionDate:
                        now(),

                    referenceType:
                        null,

                    referenceId:
                        null,

                    referenceNo:
                        'ADJ-IN-001'
                )
            );

        $stock =
            ProductStock::query()
                ->first();

        $this->assertEquals(
            120,
            (float) $stock->quantity
        );

        $this->assertEquals(
            20,
            (float) $ledger->quantity_in
        );

        $this->assertEquals(
            StockTransactionTypeEnum::ADJUSTMENT_IN,
            $ledger->transaction_type
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'reference_no' =>
                    'ADJ-IN-001',
            ]
        );
    }

    public function test_stock_adjustment_out(): void
    {
        $service =
            app(
                InventoryPostingService::class
            );

        /*
        |--------------------------------------------------------------------------
        | Opening Stock
        |--------------------------------------------------------------------------
        */

        $service->stockIn(
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
                    StockTransactionTypeEnum::OPENING_STOCK->value,

                transactionDate:
                    now(),

                referenceType:
                    null,

                referenceId:
                    null,

                referenceNo:
                    'OPEN-001'
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Adjustment Out
        |--------------------------------------------------------------------------
        */

        $ledger =
            $service->stockOut(
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
                        10,

                    transactionType:
                        StockTransactionTypeEnum::ADJUSTMENT_OUT->value,

                    transactionDate:
                        now(),

                    referenceType:
                        null,

                    referenceId:
                        null,

                    referenceNo:
                        'ADJ-OUT-001'
                )
            );

        $stock =
            ProductStock::query()
                ->first();

        $this->assertEquals(
            70,
            (float) $stock->quantity
        );

        $this->assertEquals(
            30,
            (float) $ledger->quantity_out
        );

        $this->assertEquals(
            StockTransactionTypeEnum::ADJUSTMENT_OUT,
            $ledger->transaction_type
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'reference_no' =>
                    'ADJ-OUT-001',
            ]
        );
    }
}
