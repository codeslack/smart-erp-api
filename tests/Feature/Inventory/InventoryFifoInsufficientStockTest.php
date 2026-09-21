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
use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\InventoryCostLayerConsumption;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;

class InventoryFifoInsufficientStockTest extends TestCase
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

        /*
        |--------------------------------------------------------------------------
        | Disable Negative Stock
        |--------------------------------------------------------------------------
        */

        Setting::updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'group' => 'inventory',
                'key' => 'allow_negative_stock',
            ],
            [
                'value' => 'false',
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

    public function test_fifo_insufficient_stock_does_not_mutate_inventory(): void
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
                    transactionType: 'PURCHASE',
                    transactionDate: now()->subDay(),
                    referenceNo: 'PUR-002',
                    remarks: 'Layer B',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Resolve Layers
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
        | Snapshot Existing Inventory
        |--------------------------------------------------------------------------
        */

        $stockBefore =
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
            (float) $stockBefore->quantity
        );

        $layerAQuantityBefore =
            (float) $layerA->remaining_quantity;

        $layerBQuantityBefore =
            (float) $layerB->remaining_quantity;

        $consumptionCountBefore =
            InventoryCostLayerConsumption::query()->count();

        $ledgerCountBefore =
            StockLedger::query()->count();

        /*
        |--------------------------------------------------------------------------
        | Attempt Sale Beyond Available Stock
        |--------------------------------------------------------------------------
        |
        | Available = 150
        | Requested = 160
        |
        */

        $this->expectException(
            BusinessException::class
        );

        try {
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
                    remarks: 'Insufficient FIFO stock',
                )
            );
        } finally {
            /*
            |--------------------------------------------------------------------------
            | Inventory Must Remain Unchanged
            |--------------------------------------------------------------------------
            */

            $layerA->refresh();
            $layerB->refresh();

            $stockBefore->refresh();

            $this->assertEquals(
                $layerAQuantityBefore,
                (float) $layerA->remaining_quantity
            );

            $this->assertEquals(
                $layerBQuantityBefore,
                (float) $layerB->remaining_quantity
            );

            $this->assertEquals(
                150,
                (float) $stockBefore->quantity
            );

            /*
            |--------------------------------------------------------------------------
            | No Consumption Records
            |--------------------------------------------------------------------------
            */

            $this->assertEquals(
                $consumptionCountBefore,
                InventoryCostLayerConsumption::query()->count()
            );

            /*
            |--------------------------------------------------------------------------
            | No New Stock Ledger
            |--------------------------------------------------------------------------
            */

            $this->assertEquals(
                $ledgerCountBefore,
                StockLedger::query()->count()
            );

            /*
            |--------------------------------------------------------------------------
            | Layers Remain Open
            |--------------------------------------------------------------------------
            */

            $this->assertEquals(
                InventoryCostLayerStatusEnum::OPEN,
                $layerA->status
            );

            $this->assertEquals(
                InventoryCostLayerStatusEnum::OPEN,
                $layerB->status
            );
        }
    }
}