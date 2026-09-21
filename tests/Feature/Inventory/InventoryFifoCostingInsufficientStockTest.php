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
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

use App\Modules\Inventory\Services\InventoryPostingService;

class InventoryFifoCostingInsufficientStockTest extends TestCase
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
    }

    public function test_fifo_stock_out_rejects_insufficient_stock_without_partial_consumption(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Create FIFO Layer
        |--------------------------------------------------------------------------
        |
        | Available:
        |
        | 100 × ₹10
        |
        */

        $stockInLedger =
            $this->postingService->stockIn(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 100,
                    unitCost: 10,
                    transactionType:
                        InventoryTransactionTypeEnum::PURCHASE->value,
                    transactionDate: now()->subDay(),
                    referenceNo: 'PUR-001',
                    remarks: 'FIFO insufficient stock test',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Verify Initial Layer
        |--------------------------------------------------------------------------
        */

        $layer =
            InventoryCostLayer::query()
                ->where(
                    'stock_ledger_id',
                    $stockInLedger->id
                )
                ->firstOrFail();

        $this->assertEquals(
            100,
            (float) $layer->remaining_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Initial Product Stock
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
            100,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Attempt To Sell More Than Available
        |--------------------------------------------------------------------------
        |
        | Available = 100
        | Requested = 120
        |
        | Expected:
        | BusinessException
        |
        */

        try {
            $this->postingService->stockOut(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 120,
                    unitCost: 0,
                    transactionType:
                        InventoryTransactionTypeEnum::SALE->value,
                    transactionDate: now(),
                    referenceNo: 'SAL-001',
                    remarks: 'Insufficient FIFO stock',
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
        | Layer Must Remain Completely Unchanged
        |--------------------------------------------------------------------------
        */

        $layer->refresh();

        $this->assertEquals(
            100,
            (float) $layer->remaining_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Product Stock Must Remain Unchanged
        |--------------------------------------------------------------------------
        */

        $stock->refresh();

        $this->assertEquals(
            100,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | No Consumption Records
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount(
            'inventory_cost_layer_consumptions',
            0
        );

        /*
        |--------------------------------------------------------------------------
        | No Additional Stock-Out Ledger
        |--------------------------------------------------------------------------
        |
        | The original PURCHASE ledger exists.
        | There must not be a SALE ledger because the
        | transaction was rejected.
        |
        */

        $this->assertDatabaseCount(
            'stock_ledgers',
            1
        );

        $this->assertEquals(
            $stockInLedger->id,
            StockLedger::query()
                ->firstOrFail()
                ->id
        );
    }
}