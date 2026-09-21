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
use App\Modules\Inventory\Data\InventoryReversalData;

use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\ProductStock;

use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;

class InventoryFifoFullLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

    protected InventoryPostingService $postingService;

    protected InventoryReversalService $reversalService;

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

        $this->warehouse = $this->createWarehouse();

        $this->product = $this->createProduct();

        $this->postingService =
            app(InventoryPostingService::class);

        $this->reversalService =
            app(InventoryReversalService::class);
    }

    public function test_fifo_full_lifecycle_restores_layers_and_allows_original_stock_in_reversal(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Layer A
        |--------------------------------------------------------------------------
        */

        $layerALedger =
            $this->postingService->stockIn(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 100,
                    unitCost: 10,
                    transactionType: 'PURCHASE',
                    transactionDate: now()->subDays(2),
                    referenceNo: 'PUR-001',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Layer B
        |--------------------------------------------------------------------------
        */

        $layerBLedger =
            $this->postingService->stockIn(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 50,
                    unitCost: 20,
                    transactionType: 'PURCHASE',
                    transactionDate: now()->subDay(),
                    referenceNo: 'PUR-002',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Verify Initial Layers
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
        | Sell 120
        |--------------------------------------------------------------------------
        |
        | FIFO:
        |
        | A -> 100
        | B -> 20
        |
        */

        $saleLedger =
            $this->postingService->stockOut(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 120,
                    unitCost: 0,
                    transactionType: 'SALE',
                    transactionDate: now(),
                    referenceNo: 'SAL-001',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Verify Consumed Layers
        |--------------------------------------------------------------------------
        */

        $layerA->refresh();
        $layerB->refresh();

        $this->assertEquals(
            0,
            (float) $layerA->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::EXHAUSTED,
            $layerA->status
        );

        $this->assertEquals(
            30,
            (float) $layerB->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layerB->status
        );

        /*
        |--------------------------------------------------------------------------
        | Product Stock After Sale
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
            30,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Reverse Sale
        |--------------------------------------------------------------------------
        */

        $this->reversalService->reverse(
            new InventoryReversalData(
                stockLedgerId: $saleLedger->id,
                reversalDate: now(),
                referenceNo: 'REV-SAL-001',
                remarks: 'Reverse FIFO sale',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Both Layers Must Be Restored
        |--------------------------------------------------------------------------
        */

        $layerA->refresh();
        $layerB->refresh();

        $this->assertEquals(
            100,
            (float) $layerA->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layerA->status
        );

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
        | Stock Must Be Restored
        |--------------------------------------------------------------------------
        */

        $stock->refresh();

        $this->assertEquals(
            150,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Now Original Layer A Can Be Reversed
        |--------------------------------------------------------------------------
        */

        $this->reversalService->reverse(
            new InventoryReversalData(
                stockLedgerId: $layerALedger->id,
                reversalDate: now(),
                referenceNo: 'REV-PUR-001',
                remarks: 'Reverse original FIFO stock-in',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Layer A Must Be Reversed
        |--------------------------------------------------------------------------
        */

        $layerA->refresh();

        $this->assertEquals(
            0,
            (float) $layerA->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::REVERSED,
            $layerA->status
        );

        /*
        |--------------------------------------------------------------------------
        | Layer B Must Remain Available
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
        | Final Stock
        |--------------------------------------------------------------------------
        */

        $stock->refresh();

        $this->assertEquals(
            50,
            (float) $stock->quantity
        );
    }
}