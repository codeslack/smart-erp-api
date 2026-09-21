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

class InventoryFifoThreeLayerConsumptionTest extends TestCase
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

    public function test_fifo_three_layer_consumption(): void
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
                    transactionDate: now()->subDays(3),
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
                    transactionDate: now()->subDays(2),
                    referenceNo: 'PUR-002',
                    remarks: 'Layer B',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Layer C
        |--------------------------------------------------------------------------
        |
        | 75 × ₹30 = ₹2,250
        |
        */

        $layerCLedger =
            $this->postingService->stockIn(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 75,
                    unitCost: 30,
                    transactionType: 'PURCHASE',
                    transactionDate: now()->subDay(),
                    referenceNo: 'PUR-003',
                    remarks: 'Layer C',
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

        $layerC =
            InventoryCostLayer::query()
                ->where(
                    'stock_ledger_id',
                    $layerCLedger->id
                )
                ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Sale 160 Units
        |--------------------------------------------------------------------------
        |
        | Layer A:
        | 100 × ₹10 = ₹1,000
        |
        | Layer B:
        |  50 × ₹20 = ₹1,000
        |
        | Layer C:
        |  10 × ₹30 = ₹  300
        |
        | Total COGS:
        | ₹2,300
        |
        */

        $saleLedger =
            $this->postingService->stockOut(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 160,
                    unitCost: 0,
                    transactionType: 'SALE',
                    transactionDate: now(),
                    referenceNo: 'SAL-001',
                    remarks: 'Three layer FIFO sale',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Sale Ledger
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            160,
            (float) $saleLedger->quantity_out
        );

        $this->assertEquals(
            2300,
            (float) $saleLedger->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Consumption Records
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
            3,
            $consumptions
        );

        $first =
            $consumptions->get(0);

        $second =
            $consumptions->get(1);

        $third =
            $consumptions->get(2);

        /*
        |--------------------------------------------------------------------------
        | Layer A Consumption
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            $layerA->id,
            $first->inventory_cost_layer_id
        );

        $this->assertEquals(
            100,
            (float) $first->quantity
        );

        $this->assertEquals(
            10,
            (float) $first->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $first->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Layer B Consumption
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            $layerB->id,
            $second->inventory_cost_layer_id
        );

        $this->assertEquals(
            50,
            (float) $second->quantity
        );

        $this->assertEquals(
            20,
            (float) $second->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $second->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Layer C Consumption
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            $layerC->id,
            $third->inventory_cost_layer_id
        );

        $this->assertEquals(
            10,
            (float) $third->quantity
        );

        $this->assertEquals(
            30,
            (float) $third->unit_cost
        );

        $this->assertEquals(
            300,
            (float) $third->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Layer State
        |--------------------------------------------------------------------------
        */

        $layerA->refresh();
        $layerB->refresh();
        $layerC->refresh();

        /*
        | Layer A completely consumed.
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
        | Layer B completely consumed.
        */

        $this->assertEquals(
            0,
            (float) $layerB->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::EXHAUSTED,
            $layerB->status
        );

        /*
        | Layer C partially consumed.
        */

        $this->assertEquals(
            65,
            (float) $layerC->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layerC->status
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
            65,
            (float) $stock->quantity
        );
    }
}
