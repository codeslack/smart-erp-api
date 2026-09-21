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
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;

use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;

use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\OpeningStock\Services\OpeningStockService;
use App\Modules\OpeningStock\Services\OpeningStockInventoryPostingService;

class InventorySerialPostingTest extends TestCase
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
            'inventory_tracking_type' => 'SERIAL',
        ]);

        $this->openingStockService = app(
            OpeningStockService::class
        );

        $this->postingService = app(
            OpeningStockInventoryPostingService::class
        );
    }

    public function test_opening_stock_posts_serial_and_unpost_restores_draft(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-001',
            serialNumber: 'SERIAL-001'
        );

        $serial = $this->getSerial(
            'SERIAL-001'
        );

        $this->assertSame(
            ProductSerialStatusEnum::DRAFT,
            $serial->status
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $openingStock->refresh();
        $serial->refresh();

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $openingStock->status
        );

        $this->assertSame(
            ProductSerialStatusEnum::AVAILABLE,
            $serial->status
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
            1,
            (float) $stock->quantity
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
            1,
            (float) $ledger->quantity_in
        );

        $this->assertSame(
            $serial->id,
            $ledger->product_serial_id
        );

        $this->postingService->unpost(
            $openingStock->fresh()
        );

        $openingStock->refresh();
        $serial->refresh();
        $stock->refresh();

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $openingStock->status
        );

        $this->assertSame(
            ProductSerialStatusEnum::DRAFT,
            $serial->status
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
            1,
            (float) $reversal->quantity_out
        );

        $this->assertSame(
            $serial->id,
            $reversal->product_serial_id
        );

        $this->assertSame(
            $ledger->id,
            $reversal->reversal_of_ledger_id
        );
    }

    public function test_draft_serial_becomes_available_when_opening_stock_is_posted(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-002',
            serialNumber: 'SERIAL-002'
        );

        $serial = $this->getSerial(
            'SERIAL-002'
        );

        $this->assertSame(
            ProductSerialStatusEnum::DRAFT,
            $serial->status
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $serial->refresh();

        $this->assertSame(
            ProductSerialStatusEnum::AVAILABLE,
            $serial->status
        );
    }

    public function test_same_available_serial_cannot_be_posted_again(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-003',
            serialNumber: 'SERIAL-003'
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $serial = $this->getSerial(
            'SERIAL-003'
        );

        $this->assertSame(
            ProductSerialStatusEnum::AVAILABLE,
            $serial->status
        );

        $secondOpeningStock = OpeningStock::create([
            'tenant_id' => $this->tenant->id,
            'document_no' => 'OS-SERIAL-004',
            'opening_date' => now()->toDateString(),
            'status' => DocumentStatusEnum::DRAFT->value,
            'warehouse_id' => $this->warehouse->id,
            'total_quantity' => 1,
            'total_amount' => 100,
        ]);

        $source = $secondOpeningStock->sources()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => null,
            'bill_no' => 'OS-SERIAL-004-BILL',
            'bill_date' => now()->toDateString(),
            'remarks' => 'Serial reuse test',
        ]);

        $source->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'product_variant_id' => null,
            'product_serial_id' => $serial->id,
            'quantity' => 1,
            'unit_cost' => 100,
            'total_cost' => 100,
        ]);

        $this->expectException(\Throwable::class);

        $this->postingService->post(
            $secondOpeningStock->fresh()
        );

        $this->assertDatabaseMissing(
            'stock_ledgers',
            [
                'referenceable_id' =>
                $secondOpeningStock->id,
                'product_serial_id' =>
                $serial->id,
            ]
        );
    }

    public function test_unposted_serial_can_be_posted_again(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-005',
            serialNumber: 'SERIAL-005'
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $this->postingService->unpost(
            $openingStock->fresh()
        );

        $serial = $this->getSerial(
            'SERIAL-005'
        );

        $this->assertSame(
            ProductSerialStatusEnum::DRAFT,
            $serial->status
        );

        $this->postingService->post(
            $openingStock->fresh()
        );

        $openingStock->refresh();
        $serial->refresh();

        $this->assertSame(
            DocumentStatusEnum::CONFIRMED->value,
            $openingStock->status
        );

        $this->assertSame(
            ProductSerialStatusEnum::AVAILABLE,
            $serial->status
        );

        $this->assertDatabaseCount(
            'stock_ledgers',
            3
        );
    }

    public function test_posting_serial_failure_rolls_back_status_and_inventory(): void
    {
        $openingStock = $this->createOpeningStock(
            documentNo: 'OS-SERIAL-006',
            serialNumber: 'SERIAL-006'
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

        $serial = $this->getSerial(
            'SERIAL-006'
        );

        $this->assertSame(
            DocumentStatusEnum::DRAFT->value,
            $openingStock->status
        );

        $this->assertSame(
            ProductSerialStatusEnum::DRAFT,
            $serial->status
        );

        $this->assertDatabaseMissing(
            'stock_ledgers',
            [
                'referenceable_id' =>
                $openingStock->id,
                'product_serial_id' =>
                $serial->id,
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

    protected function createOpeningStock(
        string $documentNo,
        string $serialNumber
    ): OpeningStock {
        return $this->openingStockService->create([
            'warehouse_id' => $this->warehouse->id,
            'opening_date' => now()->toDateString(),
            'remarks' => 'Serial opening stock test',

            'sources' => [
                [
                    'supplier_id' => null,
                    'bill_no' => $documentNo . '-BILL',
                    'bill_date' => now()->toDateString(),
                    'remarks' => 'Serial test source',

                    'items' => [
                        [
                            'product_id' => $this->product->id,
                            'product_variant_id' => null,
                            'quantity' => 1,
                            'unit_cost' => 100,
                            'serial_number' => $serialNumber,
                            'remarks' => 'Serial test item',
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function getSerial(
        string $serialNumber
    ): ProductSerial {
        return ProductSerial::query()
            ->where(
                'serial_number',
                $serialNumber
            )
            ->firstOrFail();
    }
}
