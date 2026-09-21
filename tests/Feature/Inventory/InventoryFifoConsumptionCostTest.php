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
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryCostLayerConsumption;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;

class InventoryFifoConsumptionCostTest extends TestCase
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

    public function test_fifo_stock_out_records_exact_consumption_cost(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Layer A
        |--------------------------------------------------------------------------
        |
        | 100 × ₹10
        | Total = ₹1,000
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
                    remarks: 'FIFO layer A',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Layer B
        |--------------------------------------------------------------------------
        |
        | 50 × ₹20
        | Total = ₹1,000
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
                    remarks: 'FIFO layer B',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Verify Initial FIFO Layers
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
            (float) $layerA->original_quantity
        );

        $this->assertEquals(
            100,
            (float) $layerA->remaining_quantity
        );

        $this->assertEquals(
            10,
            (float) $layerA->unit_cost
        );

        $this->assertEquals(
            50,
            (float) $layerB->original_quantity
        );

        $this->assertEquals(
            50,
            (float) $layerB->remaining_quantity
        );

        $this->assertEquals(
            20,
            (float) $layerB->unit_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Stock Out
        |--------------------------------------------------------------------------
        |
        | Sell 120 units.
        |
        | FIFO:
        |
        | Layer A:
        | 100 × ₹10 = ₹1,000
        |
        | Layer B:
        |  20 × ₹20 = ₹400
        |
        | Total:
        | ₹1,400
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
                    remarks: 'FIFO sale',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Verify Stock-Out Ledger
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
        | Load Consumption Records
        |--------------------------------------------------------------------------
        |
        | Your schema uses:
        |
        | stock_ledger_id
        |
        | not stock_out_ledger_id.
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

        /*
        |--------------------------------------------------------------------------
        | Exactly Two Consumption Records
        |--------------------------------------------------------------------------
        */

        $this->assertCount(
            2,
            $consumptions
        );

        /*
        |--------------------------------------------------------------------------
        | First Consumption
        |--------------------------------------------------------------------------
        |
        | Layer A:
        |
        | 100 × ₹10 = ₹1,000
        |
        */

        $firstConsumption =
            $consumptions->get(0);

        $this->assertEquals(
            $layerA->id,
            $firstConsumption->inventory_cost_layer_id
        );

        $this->assertEquals(
            $saleLedger->id,
            $firstConsumption->stock_ledger_id
        );

        $this->assertEquals(
            100,
            (float) $firstConsumption->quantity
        );

        $this->assertEquals(
            10,
            (float) $firstConsumption->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $firstConsumption->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Second Consumption
        |--------------------------------------------------------------------------
        |
        | Layer B:
        |
        | 20 × ₹20 = ₹400
        |
        */

        $secondConsumption =
            $consumptions->get(1);

        $this->assertEquals(
            $layerB->id,
            $secondConsumption->inventory_cost_layer_id
        );

        $this->assertEquals(
            $saleLedger->id,
            $secondConsumption->stock_ledger_id
        );

        $this->assertEquals(
            20,
            (float) $secondConsumption->quantity
        );

        $this->assertEquals(
            20,
            (float) $secondConsumption->unit_cost
        );

        $this->assertEquals(
            400,
            (float) $secondConsumption->total_cost
        );

        /*
        |--------------------------------------------------------------------------
        | Total FIFO Consumption Cost
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            1400,
            (float) $consumptions->sum('total_cost')
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Layer State After Sale
        |--------------------------------------------------------------------------
        */

        $layerA->refresh();
        $layerB->refresh();

        $this->assertEquals(
            0,
            (float) $layerA->remaining_quantity
        );

        $this->assertEquals(
            30,
            (float) $layerB->remaining_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Reverse Stock Out
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
        | Verify Layers Restored
        |--------------------------------------------------------------------------
        */

        $layerA->refresh();
        $layerB->refresh();

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
        | Verify Consumption Records Still Preserve History
        |--------------------------------------------------------------------------
        |
        | The consumption table has no reversed_at column.
        |
        | Therefore we do not try to mark the original consumption
        | record as inactive here.
        |
        | The reversal is represented by the reversal stock ledger
        | and the restored FIFO layer quantities.
        |
        */

        $consumptionsAfterReversal =
            InventoryCostLayerConsumption::query()
                ->where(
                    'stock_ledger_id',
                    $saleLedger->id
                )
                ->orderBy('id')
                ->get();

        $this->assertCount(
            2,
            $consumptionsAfterReversal
        );

        /*
        |--------------------------------------------------------------------------
        | Consumption History Must Remain Exact
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            100,
            (float) $consumptionsAfterReversal
                ->get(0)
                ->quantity
        );

        $this->assertEquals(
            10,
            (float) $consumptionsAfterReversal
                ->get(0)
                ->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $consumptionsAfterReversal
                ->get(0)
                ->total_cost
        );

        $this->assertEquals(
            20,
            (float) $consumptionsAfterReversal
                ->get(1)
                ->quantity
        );

        $this->assertEquals(
            20,
            (float) $consumptionsAfterReversal
                ->get(1)
                ->unit_cost
        );

        $this->assertEquals(
            400,
            (float) $consumptionsAfterReversal
                ->get(1)
                ->total_cost
        );
    }

public function test_fifo_partial_second_layer_consumption(): void
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
                remarks: 'Layer A',
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
                remarks: 'Layer B',
            )
        );

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
    | Sale 110 Units
    |--------------------------------------------------------------------------
    |
    | Layer A:
    | 100 × 10 = 1000
    |
    | Layer B:
    |  10 × 20 =  200
    |
    | Total:
    | 1200
    |
    */

    $saleLedger =
        $this->postingService->stockOut(
            new InventoryMovementData(
                productId: $this->product->id,
                productVariantId: null,
                warehouseId: $this->warehouse->id,
                quantity: 110,
                unitCost: 0,
                transactionType: 'SALE',
                transactionDate: now(),
                referenceNo: 'SAL-001',
                remarks: 'Partial second layer sale',
            )
        );

    $this->assertEquals(
        110,
        (float) $saleLedger->quantity_out
    );

    $this->assertEquals(
        1200,
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
        2,
        $consumptions
    );

    $first =
        $consumptions->get(0);

    $second =
        $consumptions->get(1);

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
        10,
        (float) $second->quantity
    );

    $this->assertEquals(
        20,
        (float) $second->unit_cost
    );

    $this->assertEquals(
        200,
        (float) $second->total_cost
    );

    /*
    |--------------------------------------------------------------------------
    | Layer State
    |--------------------------------------------------------------------------
    */

    $layerA->refresh();
    $layerB->refresh();

    $this->assertEquals(
        0,
        (float) $layerA->remaining_quantity
    );

    $this->assertEquals(
        40,
        (float) $layerB->remaining_quantity
    );

    $this->assertEquals(
        InventoryCostLayerStatusEnum::EXHAUSTED,
        $layerA->status
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
        40,
        (float) $stock->quantity
    );
}

}