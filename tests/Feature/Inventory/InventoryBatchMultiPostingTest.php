<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Enums\DocumentStatusEnum;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\Accounting\Services\AccountingSetupService;

use App\Modules\Inventory\Enums\ProductBatchStatusEnum;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

use App\Modules\OpeningStock\Models\OpeningStock;

use App\Modules\OpeningStock\Services\OpeningStockInventoryPostingService;

use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Product\Models\Product;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Warehouse\Models\Warehouse;

class InventoryBatchMultiPostingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;

    protected Warehouse $warehouse;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        $this->warehouse = $this->createWarehouse();

        $this->product = $this->createProduct([
            'inventory_tracking_type' =>
                InventoryTrackingTypeEnum::BATCH,
        ]);
    }

    public function test_opening_stock_posts_multiple_batches_and_unpost_reverses_each_batch(): void
    {
        $batchA = ProductBatch::create([
            'tenant_id' => tenantId(),
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_no' => 'BATCH-MULTI-A',
            'unit_cost' => 100,
            'original_quantity' => 0,
            'remaining_quantity' => 0,
            'status' => ProductBatchStatusEnum::DRAFT,
        ]);

        $batchB = ProductBatch::create([
            'tenant_id' => tenantId(),
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_no' => 'BATCH-MULTI-B',
            'unit_cost' => 120,
            'original_quantity' => 0,
            'remaining_quantity' => 0,
            'status' => ProductBatchStatusEnum::DRAFT,
        ]);

        $openingStock = OpeningStock::create([
            'tenant_id' => tenantId(),
            'document_no' => 'OS-MULTI-BATCH-001',
            'opening_date' => now()->toDateString(),
            'status' => DocumentStatusEnum::DRAFT->value,
            'warehouse_id' => $this->warehouse->id,
            'total_quantity' => 15,
            'total_amount' => 1700,
        ]);

        $source = $openingStock->sources()->create([
            'tenant_id' => tenantId(),
            'supplier_id' => null,
            'bill_no' => 'OS-BILL-MULTI01',
            'bill_date' => now()->toDateString(),
            'remarks' => 'Multi batch opening stock test',
        ]);

        $source->items()->createMany([
            [
                'tenant_id' => tenantId(),
                'product_id' => $this->product->id,
                'product_batch_id' => $batchA->id,
                'quantity' => 5,
                'unit_cost' => 100,
                'total_cost' => 500,
            ],
            [
                'tenant_id' => tenantId(),
                'product_id' => $this->product->id,
                'product_batch_id' => $batchB->id,
                'quantity' => 10,
                'unit_cost' => 120,
                'total_cost' => 1200,
            ],
        ]);        

        $service = app(
            OpeningStockInventoryPostingService::class
        );

        /*
        |--------------------------------------------------------------------------
        | Initial Batch State
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            'DRAFT',
            $batchA->status->value
        );

        $this->assertSame(
            'DRAFT',
            $batchB->status->value
        );

        $this->assertEquals(
            0,
            (float) $batchA->original_quantity
        );

        $this->assertEquals(
            0,
            (float) $batchA->remaining_quantity
        );

        $this->assertEquals(
            0,
            (float) $batchB->original_quantity
        );

        $this->assertEquals(
            0,
            (float) $batchB->remaining_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Post
        |--------------------------------------------------------------------------
        */

        $service->post(
            $openingStock->fresh()
        );

        $openingStock->refresh();

        $batchA->refresh();
        $batchB->refresh();

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $openingStock->status
        );

        $this->assertSame(
            'ACTIVE',
            $batchA->status->value
        );

        $this->assertSame(
            'ACTIVE',
            $batchB->status->value
        );

        $this->assertEquals(
            5,
            (float) $batchA->original_quantity
        );

        $this->assertEquals(
            5,
            (float) $batchA->remaining_quantity
        );

        $this->assertEquals(
            10,
            (float) $batchB->original_quantity
        );

        $this->assertEquals(
            10,
            (float) $batchB->remaining_quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Product Stock
        |--------------------------------------------------------------------------
        */

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
            15,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Opening Stock Ledgers
        |--------------------------------------------------------------------------
        */

        $ledgers = StockLedger::query()
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
            ->get();

        $this->assertCount(
            2,
            $ledgers
        );

        $this->assertTrue(
            $ledgers->contains(
                fn (StockLedger $ledger): bool =>
                    $ledger->product_batch_id === $batchA->id
                    && (float) $ledger->quantity_in === 5.0
            )
        );

        $this->assertTrue(
            $ledgers->contains(
                fn (StockLedger $ledger): bool =>
                    $ledger->product_batch_id === $batchB->id
                    && (float) $ledger->quantity_in === 10.0
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Unpost
        |--------------------------------------------------------------------------
        */

        $service->unpost(
            $openingStock->fresh()
        );

        $openingStock->refresh();

        $batchA->refresh();
        $batchB->refresh();
        $stock->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $openingStock->status
        );

        $this->assertSame(
            'DRAFT',
            $batchA->status->value
        );

        $this->assertSame(
            'DRAFT',
            $batchB->status->value
        );

        $this->assertEquals(
            0,
            (float) $batchA->original_quantity
        );

        $this->assertEquals(
            0,
            (float) $batchA->remaining_quantity
        );

        $this->assertEquals(
            0,
            (float) $batchB->original_quantity
        );

        $this->assertEquals(
            0,
            (float) $batchB->remaining_quantity
        );

        $this->assertEquals(
            0,
            (float) $stock->quantity
        );

        /*
        |--------------------------------------------------------------------------
        | Reversal Ledgers
        |--------------------------------------------------------------------------
        */

        $reversals = StockLedger::query()
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
            ->get();

        $this->assertCount(
            2,
            $reversals
        );
    }
}