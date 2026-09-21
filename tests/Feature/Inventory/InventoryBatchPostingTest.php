<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesProduct;
use Tests\Support\CreatesWarehouse;

use App\Core\Tenant\TenantManager;
use App\Core\Enums\DocumentStatusEnum;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Enums\ProductBatchStatusEnum;
use App\Modules\Inventory\Services\InventoryStockInService;

use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\OpeningStock\Services\OpeningStockService;
use App\Modules\OpeningStock\Services\OpeningStockInventoryPostingService;

use App\Modules\Accounting\Services\AccountingSetupService;

class InventoryBatchPostingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesProduct;
    use CreatesWarehouse;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected Product $product;

    protected OpeningStockService $openingStockService;
    protected OpeningStockInventoryPostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(
            TenantManager::class
        )->setTenant($this->tenant);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        $this->warehouse = $this->createWarehouse();

        $this->product = $this->createProduct([
            'inventory_tracking_type' =>
                InventoryTrackingTypeEnum::BATCH,
        ]);

        $this->openingStockService = app(
            OpeningStockService::class
        );

        $this->postingService = app(
            OpeningStockInventoryPostingService::class
        );
    }

    public function test_stock_in_increases_active_batch_quantity(): void
    {
        $batch = ProductBatch::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_no' => 'BATCH-001',
            'unit_cost' => 100,
            'original_quantity' => 10,
            'remaining_quantity' => 10,
            'status' => ProductBatchStatusEnum::ACTIVE->value,
        ]);

        $movement = new InventoryMovementData(
            productId: $this->product->id,
            productVariantId: null,
            warehouseId: $this->warehouse->id,
            quantity: 5,
            unitCost: 100,
            transactionType:
                InventoryTransactionTypeEnum::OPENING_STOCK->value,
            transactionDate: now(),
            batchId: $batch->id,
        );

        app(InventoryStockInService::class)
            ->post($movement);

        $batch->refresh();

        $this->assertEquals(
            10,
            (float) $batch->original_quantity
        );

        $this->assertEquals(
            15,
            (float) $batch->remaining_quantity
        );

        $this->assertEquals(
            ProductBatchStatusEnum::ACTIVE,
            $batch->status
        );
    }

    public function test_stock_in_creates_product_stock_and_ledger(): void
    {
        $batch = ProductBatch::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_no' => 'BATCH-002',
            'unit_cost' => 100,
            'original_quantity' => 0,
            'remaining_quantity' => 0,
            'status' => ProductBatchStatusEnum::ACTIVE->value,
        ]);

        $movement = new InventoryMovementData(
            productId: $this->product->id,
            productVariantId: null,
            warehouseId: $this->warehouse->id,
            quantity: 5,
            unitCost: 100,
            transactionType:
                InventoryTransactionTypeEnum::OPENING_STOCK->value,
            transactionDate: now(),
            batchId: $batch->id,
        );

        app(InventoryStockInService::class)
            ->post($movement);

        $stock = ProductStock::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->whereNull('product_variant_id')
            ->first();

        $this->assertNotNull($stock);

        $this->assertEquals(
            5,
            (float) $stock->quantity
        );

        $ledger = StockLedger::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_batch_id', $batch->id)
            ->first();

        $this->assertNotNull($ledger);

        $this->assertEquals(
            5,
            (float) $ledger->quantity_in
        );

        $this->assertEquals(
            100,
            (float) $ledger->unit_cost
        );

        $this->assertEquals(
            500,
            (float) $ledger->total_cost
        );

        $this->assertEquals(
            InventoryTransactionTypeEnum::OPENING_STOCK,
            $ledger->transaction_type
        );
    }

    public function test_opening_stock_creates_draft_batch(): void
    {
        $openingStock = $this->createOpeningStock();

        $openingStock->load([
            'sources.items',
        ]);

        $item = $openingStock
            ->sources
            ->first()
            ->items
            ->first();

        $this->assertNotNull(
            $item->product_batch_id
        );

        $batch = ProductBatch::find(
            $item->product_batch_id
        );

        $this->assertNotNull($batch);

        $this->assertEquals(
            ProductBatchStatusEnum::DRAFT,
            $batch->status
        );

        $this->assertEquals(
            'OPEN-BATCH-001',
            $batch->batch_no
        );

        $this->assertEquals(
            0,
            (float) $batch->original_quantity
        );

        $this->assertEquals(
            0,
            (float) $batch->remaining_quantity
        );
    }

    public function test_posting_opening_stock_activates_batch_and_creates_inventory(): void
    {
        $openingStock = $this->createOpeningStock();

        $openingStock->load([
            'sources.items',
        ]);

        $item = $openingStock
            ->sources
            ->first()
            ->items
            ->first();

        $batch = ProductBatch::findOrFail(
            $item->product_batch_id
        );

        $this->assertEquals(
            ProductBatchStatusEnum::DRAFT,
            $batch->status
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $openingStock->refresh();
        $batch->refresh();

        $this->assertEquals(
            DocumentStatusEnum::CONFIRMED->value,
            $openingStock->status
        );

        $this->assertEquals(
            ProductBatchStatusEnum::ACTIVE,
            $batch->status
        );

        $this->assertEquals(
            5,
            (float) $batch->original_quantity
        );

        $this->assertEquals(
            5,
            (float) $batch->remaining_quantity
        );

        $this->assertDatabaseHas(
            'product_stocks',
            [
                'tenant_id' => $this->tenant->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 5,
            ]
        );

        $this->assertDatabaseHas(
            'stock_ledgers',
            [
                'tenant_id' => $this->tenant->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'product_batch_id' => $batch->id,
                'quantity_in' => 5,
                'transaction_type' =>
                    InventoryTransactionTypeEnum::OPENING_STOCK->value,
            ]
        );
    }

    public function test_unpost_restores_opening_stock_batch_to_draft(): void
    {
        $openingStock = $this->createOpeningStock();

        $openingStock->load([
            'sources.items',
        ]);

        $item = $openingStock
            ->sources
            ->first()
            ->items
            ->first();

        $batch = ProductBatch::findOrFail(
            $item->product_batch_id
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $batch->refresh();

        $this->assertEquals(
            ProductBatchStatusEnum::ACTIVE,
            $batch->status
        );

        $this->assertEquals(
            5,
            (float) $batch->remaining_quantity
        );

        $this->postingService->unpost(
            $openingStock->fresh()
        );

        $openingStock->refresh();
        $batch->refresh();

        $this->assertEquals(
            DocumentStatusEnum::DRAFT->value,
            $openingStock->status
        );

        $this->assertEquals(
            ProductBatchStatusEnum::DRAFT,
            $batch->status
        );

        $this->assertEquals(
            0,
            (float) $batch->remaining_quantity
        );

        $stock = ProductStock::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertTrue(
            $stock === null
            || (float) $stock->quantity === 0.0
        );
    }

    public function test_opening_stock_can_be_reposted_after_unpost(): void
    {
        $openingStock = $this->createOpeningStock();

        $openingStock->load([
            'sources.items',
        ]);

        $item = $openingStock
            ->sources
            ->first()
            ->items
            ->first();

        $batch = ProductBatch::findOrFail(
            $item->product_batch_id
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $this->postingService->unpost(
            $openingStock->fresh()
        );

        $batch->refresh();

        $this->assertEquals(
            ProductBatchStatusEnum::DRAFT,
            $batch->status
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $openingStock->refresh();
        $batch->refresh();

        $this->assertEquals(
            DocumentStatusEnum::CONFIRMED->value,
            $openingStock->status
        );

        $this->assertEquals(
            ProductBatchStatusEnum::ACTIVE,
            $batch->status
        );

        $this->assertEquals(
            5,
            (float) $batch->remaining_quantity
        );

        $ledgers = StockLedger::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(
            3,
            $ledgers
        );

        $this->assertEquals(
            5,
            (float) $ledgers[0]->quantity_in
        );

        $this->assertEquals(
            5,
            (float) $ledgers[1]->quantity_out
        );

        $this->assertEquals(
            5,
            (float) $ledgers[2]->quantity_in
        );
    }

    public function test_opening_stock_rolls_back_when_batch_posting_fails(): void
    {
        $openingStock = $this->createOpeningStock();

        $openingStock->load([
            'sources.items',
        ]);

        $item = $openingStock
            ->sources
            ->first()
            ->items
            ->first();

        $batch = ProductBatch::findOrFail(
            $item->product_batch_id
        );

        $item->update([
            'quantity' => 0,
            'total_cost' => 0,
        ]);

        try {
            $this->postingService->post(
                $openingStock->fresh()
            );

            $this->fail(
                'Opening stock posting should have failed.'
            );
        } catch (\Throwable) {
            // Expected.
        }

        $openingStock->refresh();
        $batch->refresh();

        $this->assertEquals(
            DocumentStatusEnum::DRAFT->value,
            $openingStock->status
        );

        $this->assertEquals(
            ProductBatchStatusEnum::DRAFT,
            $batch->status
        );

        $this->assertEquals(
            0,
            (float) $batch->remaining_quantity
        );

        $this->assertDatabaseMissing(
            'product_stocks',
            [
                'tenant_id' => $this->tenant->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
            ]
        );

        $this->assertDatabaseMissing(
            'stock_ledgers',
            [
                'tenant_id' => $this->tenant->id,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'transaction_type' =>
                    InventoryTransactionTypeEnum::OPENING_STOCK->value,
            ]
        );
    }

    protected function createOpeningStock(): OpeningStock
    {
        return $this->openingStockService->create([
            'warehouse_id' => $this->warehouse->id,
            'opening_date' => now()->toDateString(),
            'remarks' => 'Batch opening stock test',

            'sources' => [
                [
                    'supplier_id' => null,
                    'bill_no' => 'OPEN-BATCH-BILL-001',
                    'bill_date' => now()->toDateString(),
                    'remarks' => 'Batch source',

                    'items' => [
                        [
                            'product_id' => $this->product->id,
                            'product_variant_id' => null,
                            'quantity' => 5,
                            'unit_cost' => 100,
                            'batch_no' => 'OPEN-BATCH-001',
                            'manufacturing_date' => null,
                            'expiry_date' => null,
                            'remarks' => 'Batch item',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
