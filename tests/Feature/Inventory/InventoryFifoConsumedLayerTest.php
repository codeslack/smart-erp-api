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
use App\Modules\Inventory\Data\InventoryReversalData;

use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Models\ProductStock;

use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;

use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;

class InventoryFifoConsumedLayerTest extends TestCase
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

    public function test_consumed_fifo_layer_cannot_be_reversed(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Stock In
        |--------------------------------------------------------------------------
        |
        | 100 @ 10
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
                    transactionType: 'PURCHASE',
                    transactionDate: now()->subDay(),
                    referenceType: null,
                    referenceId: null,
                    referenceNo: 'PUR-001',
                    batchId: null,
                    serialId: null,
                    remarks: 'FIFO stock in',
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Verify FIFO Layer
        |--------------------------------------------------------------------------
        */

        $layer =
            InventoryCostLayer::query()
                ->where(
                    'stock_ledger_id',
                    $stockInLedger->id
                )
                ->first();

        $this->assertNotNull(
            $layer
        );

        $this->assertEquals(
            100,
            (float) $layer->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layer->status
        );

        /*
        |--------------------------------------------------------------------------
        | Stock Out
        |--------------------------------------------------------------------------
        |
        | Consume 50 from the FIFO layer.
        |
        */

        $saleLedger =
            $this->postingService->stockOut(
                new InventoryMovementData(
                    productId: $this->product->id,
                    productVariantId: null,
                    warehouseId: $this->warehouse->id,
                    quantity: 50,
                    unitCost: 0,
                    transactionType: 'SALE',
                    transactionDate: now(),
                    referenceType: null,
                    referenceId: null,
                    referenceNo: 'SAL-001',
                    batchId: null,
                    serialId: null,
                    remarks: 'FIFO sale',
                )
            );

        $this->assertNotNull(
            $saleLedger
        );

        /*
        |--------------------------------------------------------------------------
        | Verify Layer Is Partially Consumed
        |--------------------------------------------------------------------------
        */

        $layer->refresh();

        $this->assertEquals(
            50,
            (float) $layer->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layer->status
        );

        /*
        |--------------------------------------------------------------------------
        | Attempt To Reverse Original Stock-In
        |--------------------------------------------------------------------------
        */

        try {
            $this->reversalService->reverse(
                new InventoryReversalData(
                    stockLedgerId: $stockInLedger->id,
                    reversalDate: now(),
                    referenceType: null,
                    referenceId: null,
                    referenceNo: 'REV-PUR-001',
                    remarks: 'Attempt to reverse consumed FIFO layer',
                )
            );

            $this->fail(
                'Expected consumed FIFO layer reversal to fail.'
            );
        } catch (BusinessException $exception) {
            $this->assertEquals(
                'STOCK_IN_ALREADY_CONSUMED',
                $exception->errorCode()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Layer Must Remain Unchanged
        |--------------------------------------------------------------------------
        */

        $layer->refresh();

        $this->assertEquals(
            50,
            (float) $layer->remaining_quantity
        );

        $this->assertEquals(
            InventoryCostLayerStatusEnum::OPEN,
            $layer->status
        );

        /*
        |--------------------------------------------------------------------------
        | Product Stock Must Remain
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
            50,
            (float) $stock->quantity
        );
    }
}