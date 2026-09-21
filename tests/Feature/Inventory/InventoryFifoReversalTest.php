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

use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryCostLayerConsumption;
use App\Modules\Inventory\Models\ProductStock;

use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;

class InventoryFifoReversalTest extends TestCase
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

        $this->tenant =
            $this->createTestTenant();

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

        $this->reversalService =
            app(InventoryReversalService::class);
    }

    public function test_reversing_fifo_stock_out_restores_consumed_layers(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Layer 1
        |--------------------------------------------------------------------------
        |
        | 100 @ 10
        |
        */

        $this->postingService->stockIn(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 100,
                unitCost: 10,
                transactionType: 'PURCHASE',
                transactionDate: now()->subDays(2),
                referenceType: null,
                referenceId: null,
                referenceNo: 'PUR-001',
                batchId: null,
                serialId: null,
                remarks: 'FIFO Layer 1',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Layer 2
        |--------------------------------------------------------------------------
        |
        | 50 @ 20
        |
        */

        $this->postingService->stockIn(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 50,
                unitCost: 20,
                transactionType: 'PURCHASE',
                transactionDate: now()->subDay(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'PUR-002',
                batchId: null,
                serialId: null,
                remarks: 'FIFO Layer 2',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Stock Out
        |--------------------------------------------------------------------------
        |
        | Sell 120
        |
        | Layer 1 → 100
        | Layer 2 → 20
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
                    referenceType: null,
                    referenceId: null,
                    referenceNo: 'SAL-001',
                    batchId: null,
                    serialId: null,
                    remarks: 'FIFO Sale',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Verify consumed state before reversal
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

        $this->assertEquals(
            0,
            (float) $layer1->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::EXHAUSTED,
            $layer1->status
        );

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
        | Verify Consumption History
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
        |--------------------------------------------------------------------------
        | Reverse Sale
        |--------------------------------------------------------------------------
        */

        $this->reversalService->reverse(
            new InventoryReversalData(
                stockLedgerId: $saleLedger->id,
                reversalDate: now(),
                referenceType: null,
                referenceId: null,
                referenceNo: 'REV-SAL-001',
                remarks: 'Reverse FIFO Sale',
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Refresh Layers
        |--------------------------------------------------------------------------
        */

        $layer1->refresh();
        $layer2->refresh();

        /*
        |--------------------------------------------------------------------------
        | Layer 1 Restored
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            100,
            (float) $layer1->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layer1->status
        );

        /*
        |--------------------------------------------------------------------------
        | Layer 2 Restored
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            50,
            (float) $layer2->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layer2->status
        );

        /*
        |--------------------------------------------------------------------------
        | Product Stock Restored
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
            150,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Reversal Ledger
        |--------------------------------------------------------------------------
        */

        $reversalLedger =
            StockLedger::query()
                ->where(
                    'reversal_of_ledger_id',
                    $saleLedger->id
                )
                ->first();

        $this->assertNotNull(
            $reversalLedger
        );

        $this->assertEquals(
            150,
            (float) $reversalLedger->balance_quantity
        );
    }
}