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
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryCostLayerConsumption;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;

class InventoryFifoExactLayerBoundaryTest extends TestCase
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

        /*
        |--------------------------------------------------------------------------
        | Force FIFO Costing
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

        $this->warehouse =
            $this->createWarehouse();

        $this->product =
            $this->createProduct();

        $this->postingService =
            app(InventoryPostingService::class);

        $this->reversalService =
            app(InventoryReversalService::class);
    }

    public function test_fifo_exact_layer_boundary(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Layer A
        |--------------------------------------------------------------------------
        |
        | 100 × ₹10 = ₹1,000
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
                    transactionType: 'PURCHASE',
                    transactionDate: now()->subDays(2),
                    referenceNo: 'PUR-001',
                    remarks: 'Layer A',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Layer B
        |--------------------------------------------------------------------------
        |
        | 50 × ₹20 = ₹1,000
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
                    transactionType: 'PURCHASE',
                    transactionDate: now()->subDay(),
                    referenceNo: 'PUR-002',
                    remarks: 'Layer B',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Resolve Cost Layers
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
        | Sale Exactly Equals Layer A
        |--------------------------------------------------------------------------
        |
        | Sale = 100
        |
        | Layer A:
        | 100 × ₹10 = ₹1,000
        |
        | Layer B:
        | untouched
        |
        */

        $saleLedger =
            $this->postingService->stockOut(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 100,
                    unitCost: 0,
                    transactionType: 'SALE',
                    transactionDate: now(),
                    referenceNo: 'SAL-001',
                    remarks: 'Exact FIFO layer boundary',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Sale Ledger
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            100,
            (float) $saleLedger->quantity_out
        );

        $this->assertEquals(
            1000,
            (float) $saleLedger->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Consumption Records
        |--------------------------------------------------------------------------
        |
        | Only Layer A should be consumed.
        |
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
            1,
            $consumptions
        );

        $consumption =
            $consumptions->first();

        /*
        |--------------------------------------------------------------------------
        | Layer A Consumption
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            $layerA->id,
            $consumption->inventory_cost_layer_id
        );

        $this->assertEquals(
            100,
            (float) $consumption->quantity
        );

        $this->assertEquals(
            10,
            (float) $consumption->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $consumption->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Layer State
        |--------------------------------------------------------------------------
        */

        $layerA->refresh();
        $layerB->refresh();

        /*
        | Layer A must be exhausted.
        */

        $this->assertEquals(
            0,
            (float) $layerA->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::EXHAUSTED,
            $layerA->status
        );

        /*
        | Layer B must remain completely untouched.
        */

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
        | Total Remaining Stock
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
            50,
            (float) $stock->quantity
        );
    }
}
