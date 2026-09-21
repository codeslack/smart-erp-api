<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Settings\Models\Setting;

use App\Modules\Inventory\Data\InventoryMovementData;

use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryCostLayerConsumption;
use App\Modules\Inventory\Models\ProductStock;

use App\Modules\Inventory\Services\InventoryPostingService;

class InventoryFifoPartialLayerConsumptionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

    protected InventoryPostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

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

        $this->warehouse =
            $this->createWarehouse();

        $this->product =
            $this->createProduct();

        $this->postingService =
            app(InventoryPostingService::class);
    }

    public function test_fifo_partial_stock_out_consumes_only_part_of_first_layer(): void
    {
        /*
        |--------------------------------------------------------------------------
        | FIFO Layer A
        |--------------------------------------------------------------------------
        |
        | 100 × ₹10
        |
        */

        $layerALedger =
            $this->postingService->stockIn(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 100,
                    unitCost: 10,
                    transactionType:
                        InventoryTransactionTypeEnum::PURCHASE->value,
                    transactionDate: now()->subDays(2),
                    referenceNo: 'PUR-001',
                    remarks: 'FIFO partial layer A',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | FIFO Layer B
        |--------------------------------------------------------------------------
        |
        | 50 × ₹20
        |
        */

        $layerBLedger =
            $this->postingService->stockIn(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 50,
                    unitCost: 20,
                    transactionType:
                        InventoryTransactionTypeEnum::PURCHASE->value,
                    transactionDate: now()->subDay(),
                    referenceNo: 'PUR-002',
                    remarks: 'FIFO partial layer B',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Load Layers
        |--------------------------------------------------------------------------
        */

        $layerA =
            InventoryCostLayer::query()
                ->where(
                    'stock_ledger_id',
                    $layerALedger->id
                )
                ->firstOrFail();

        $layerB =
            InventoryCostLayer::query()
                ->where(
                    'stock_ledger_id',
                    $layerBLedger->id
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Verify Initial Layers
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            100,
            (float) $layerA->remaining_quantity
        );

        $this->assertEquals(
            50,
            (float) $layerB->remaining_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Initial Stock
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
                ->firstOrFail();

        $this->assertEquals(
            150,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Partial FIFO Stock Out
        |--------------------------------------------------------------------------
        |
        | Sell 40.
        |
        | Layer A:
        | 100 → 60
        |
        | Layer B:
        | 50 → 50
        |
        */

        $saleLedger =
            $this->postingService->stockOut(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 40,
                    unitCost: 0,
                    transactionType:
                        InventoryTransactionTypeEnum::SALE->value,
                    transactionDate: now(),
                    referenceNo: 'SAL-001',
                    remarks: 'FIFO partial consumption',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Product Stock
        |--------------------------------------------------------------------------
        */

        $stock->refresh();

        $this->assertEquals(
            110,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Layer A Partially Consumed
        |--------------------------------------------------------------------------
        */

        $layerA->refresh();

        $this->assertEquals(
            60,
            (float) $layerA->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layerA->status
        );

        /*
        |--------------------------------------------------------------------------
        | Layer B Completely Untouched
        |--------------------------------------------------------------------------
        */

        $layerB->refresh();

        $this->assertEquals(
            50,
            (float) $layerB->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layerB->status
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Consumption
        |--------------------------------------------------------------------------
        */

        $consumption =
            InventoryCostLayerConsumption::query()
                ->where(
                    'stock_ledger_id',
                    $saleLedger->id
                )
                ->firstOrFail();

        $this->assertEquals(
            $layerA->id,
            $consumption->inventory_cost_layer_id
        );

        $this->assertEquals(
            40,
            (float) $consumption->quantity
        );

        $this->assertEquals(
            10,
            (float) $consumption->unit_cost
        );

        $this->assertEquals(
            400,
            (float) $consumption->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Only One Consumption Record
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1,
            InventoryCostLayerConsumption::query()
                ->where(
                    'stock_ledger_id',
                    $saleLedger->id
                )
                ->count()
        );
    }
}
