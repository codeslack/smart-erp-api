<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Enums\DocumentStatusEnum;

use App\Modules\Settings\Models\Setting;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Product\Models\Product;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Accounting\Services\AccountingSetupService;

use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\OpeningStock\Services\OpeningStockService;
use App\Modules\OpeningStock\Services\OpeningStockInventoryPostingService;

class InventoryNoneTrackingPostingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;

    protected Product $product;

    protected Warehouse $warehouse;

    protected OpeningStockService $openingStockService;

    protected OpeningStockInventoryPostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

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

        $this->product = $this->createProduct([
            'tenant_id' => $this->tenant->id,
            'inventory_tracking_type' => 'NONE',
        ]);

        $this->openingStockService = app(
            OpeningStockService::class
        );

        $this->postingService = app(
            OpeningStockInventoryPostingService::class
        );
    }

    public function test_opening_stock_posts_none_tracking_product(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-NONE-001'
        );

        $this->assertDatabaseCount(
            'product_batches',
            0
        );

        $this->assertDatabaseCount(
            'product_serials',
            0
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $openingStock->refresh();

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $openingStock->status
        );

        $stock = ProductStock::query()
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
            10,
            (float) $stock->quantity
        );

        $this->assertEquals(
            100,
            (float) $stock->average_cost
        );

        $ledger = StockLedger::query()
            ->where(
                'referenceable_type',
                OpeningStock::class
            )
            ->where(
                'referenceable_id',
                $openingStock->id
            )
            ->where(
                'transaction_type',
                InventoryTransactionTypeEnum::OPENING_STOCK
            )
            ->firstOrFail();

        $this->assertEquals(
            10,
            (float) $ledger->quantity_in
        );

        $this->assertEquals(
            100,
            (float) $ledger->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $ledger->total_cost
        );

        $this->assertNull(
            $ledger->product_batch_id
        );

        $this->assertNull(
            $ledger->product_serial_id
        );
    }

    public function test_unpost_reverses_none_tracking_stock(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-NONE-002'
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $stock = ProductStock::query()
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
            10,
            (float) $stock->quantity
        );

        $this->postingService->unpost(
            $openingStock->fresh()
        );

        $openingStock->refresh();
        $stock->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $openingStock->status
        );

        $this->assertEquals(
            0,
            (float) $stock->quantity
        );

        $reversal = StockLedger::query()
            ->where(
                'referenceable_type',
                OpeningStock::class
            )
            ->where(
                'referenceable_id',
                $openingStock->id
            )
            ->where(
                'transaction_type',
                InventoryTransactionTypeEnum::REVERSAL_OUT
            )
            ->firstOrFail();

        $this->assertEquals(
            10,
            (float) $reversal->quantity_out
        );

        $this->assertNull(
            $reversal->product_batch_id
        );

        $this->assertNull(
            $reversal->product_serial_id
        );
    }

    public function test_unposted_none_tracking_stock_can_be_posted_again(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-NONE-003'
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $this->postingService->unpost(
            $openingStock->fresh()
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $openingStock->refresh();

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $openingStock->status
        );

        $stock = ProductStock::query()
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
            10,
            (float) $stock->quantity
        );

        $this->assertDatabaseCount(
            'stock_ledgers',
            3
        );
    }

    public function test_none_tracking_product_cannot_receive_batch_or_serial(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-NONE-004'
        );

        $batch = ProductBatch::query()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_no' => 'NONE-INVALID-BATCH',
            'unit_cost' => 100,
            'original_quantity' => 0,
            'remaining_quantity' => 0,
        ]);

        $item = $openingStock
            ->sources
            ->first()
            ->items
            ->first();

        $item->update([
            'product_batch_id' => $batch->id,
        ]);

        $this->expectException(\Throwable::class);

        $this->postingService->post(
            $openingStock->fresh()
        );

        $this->assertDatabaseMissing(
            'stock_ledgers',
            [
                'referenceable_id' =>
                $openingStock->id,
            ]
        );

        $this->assertDatabaseMissing(
            'product_stocks',
            [
                'product_id' =>
                $this->product->id,
                'warehouse_id' =>
                $this->warehouse->id,
            ]
        );
    }

    public function test_posting_failure_rolls_back_none_tracking_inventory(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-NONE-005'
        );

        $item = $openingStock
            ->sources
            ->first()
            ->items
            ->first();

        $item->update([
            'quantity' => 0,
        ]);

        $this->expectException(\Throwable::class);

        $this->postingService->post(
            $openingStock->fresh()
        );

        $openingStock->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $openingStock->status
        );

        $this->assertDatabaseMissing(
            'stock_ledgers',
            [
                'referenceable_id' =>
                $openingStock->id,
            ]
        );

        $this->assertDatabaseMissing(
            'product_stocks',
            [
                'product_id' =>
                $this->product->id,
                'warehouse_id' =>
                $this->warehouse->id,
            ]
        );

        $this->assertDatabaseCount(
            'product_batches',
            0
        );

        $this->assertDatabaseCount(
            'product_serials',
            0
        );
    }

    protected function createOpeningStock(
        string $documentNo
    ): OpeningStock {
        return $this->openingStockService->create([
            'warehouse_id' => $this->warehouse->id,
            'opening_date' => now()->toDateString(),
            'remarks' => 'None tracking opening stock test',

            'sources' => [
                [
                    'supplier_id' => null,
                    'bill_no' => $documentNo . '-BILL',
                    'bill_date' => now()->toDateString(),
                    'remarks' => 'None tracking test source',

                    'items' => [
                        [
                            'product_id' =>
                            $this->product->id,
                            'product_variant_id' => null,
                            'quantity' => 10,
                            'unit_cost' => 100,
                            'remarks' =>
                            'None tracking test item',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
