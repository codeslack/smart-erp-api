<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Tenant\TenantManager;
use App\Core\Exceptions\BusinessException;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Settings\Models\Setting;

use App\Modules\Inventory\Data\InventoryMovementData;

use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryCostLayerConsumption;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

use App\Modules\Inventory\Services\InventoryPostingService;

class InventoryFifoMultiLayerInsufficientStockTest extends TestCase
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

    public function test_fifo_multi_layer_stock_out_rejects_without_partial_consumption(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Layer A
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
                    remarks: 'FIFO layer A',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Layer B
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
            (float) $layerA->remaining_quantity
        );

        $this->assertEquals(
            50,
            (float) $layerB->remaining_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Total Product Stock
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
        | Attempt To Sell 170
        |--------------------------------------------------------------------------
        |
        | Available = 150
        | Requested = 170
        |
        | The transaction must fail BEFORE any FIFO
        | layer is consumed.
        |
        */

        try {
            $this->postingService->stockOut(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 170,
                    unitCost: 0,
                    transactionType:
                        InventoryTransactionTypeEnum::SALE->value,
                    transactionDate: now(),
                    referenceNo: 'SAL-001',
                    remarks: 'Insufficient multi-layer FIFO stock',
                )
            );

            $this->fail(
                'Expected insufficient stock exception.'
            );
        } catch (BusinessException $exception) {
            $this->assertStringContainsString(
                'stock',
                strtolower($exception->getMessage())
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Layer A Must Not Be Partially Consumed
        |--------------------------------------------------------------------------
        */

        $layerA->refresh();

        $this->assertEquals(
            100,
            (float) $layerA->remaining_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Layer B Must Not Be Partially Consumed
        |--------------------------------------------------------------------------
        */

        $layerB->refresh();

        $this->assertEquals(
            50,
            (float) $layerB->remaining_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Product Stock Must Remain Unchanged
        |--------------------------------------------------------------------------
        */

        $stock->refresh();

        $this->assertEquals(
            150,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | No FIFO Consumption Records
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount(
            'inventory_cost_layer_consumptions',
            0
        );

        /*
        |--------------------------------------------------------------------------
        | No SALE Ledger
        |--------------------------------------------------------------------------
        |
        | Only the two PURCHASE ledgers should exist.
        |
        */

        $this->assertDatabaseCount(
            'stock_ledgers',
            2
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'id' => $layerALedger->id,
                'transaction_type' =>
                    InventoryTransactionTypeEnum::PURCHASE->value,
            ]
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'id' => $layerBLedger->id,
                'transaction_type' =>
                    InventoryTransactionTypeEnum::PURCHASE->value,
            ]
        );
    }
}