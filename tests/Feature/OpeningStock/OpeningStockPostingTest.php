<?php

namespace Tests\Feature\OpeningStock;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Enums\DocumentStatusEnum;
use App\Core\Exceptions\BusinessException;
use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Warehouse\Models\Warehouse;
use App\Modules\Product\Models\Product;

use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\OpeningStock\Services\OpeningStockService;
use App\Modules\OpeningStock\Services\OpeningStockInventoryPostingService;

use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalEntryStatusEnum;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

class OpeningStockPostingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;
    protected Warehouse $warehouse;
    protected Product $product;

    protected OpeningStockService $openingStockService;
    protected OpeningStockInventoryPostingService $postingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        $this->warehouse = $this->createWarehouse();
        $this->product = $this->createProduct();

        $this->openingStockService = app(
            OpeningStockService::class
        );

        $this->postingService = app(
            OpeningStockInventoryPostingService::class
        );
    }

    public function test_opening_stock_posts_inventory(): void
    {
        $openingStock = $this->createOpeningStock();

        $this->postingService->post(
            $openingStock->fresh()
        );

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(10, (float) $stock->quantity);
        $this->assertEquals(100, (float) $stock->average_cost);

        $ledger = StockLedger::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where(
                'transaction_type',
                InventoryTransactionTypeEnum::OPENING_STOCK->value
            )
            ->first();

        $this->assertNotNull($ledger);
        $this->assertEquals(10, (float) $ledger->quantity_in);
        $this->assertEquals(0, (float) $ledger->quantity_out);
        $this->assertEquals(100, (float) $ledger->unit_cost);
        $this->assertEquals(1000, (float) $ledger->total_cost);

        $this->assertEquals(
            InventoryTransactionTypeEnum::OPENING_STOCK,
            $ledger->transaction_type
        );

        $this->assertEquals(
            DocumentStatusEnum::CONFIRMED->value,
            $openingStock->fresh()->status
        );
    }

    public function test_opening_stock_creates_accounting_entry(): void
    {
        $openingStock = $this->createOpeningStock();

        $this->postingService->post(
            $openingStock->fresh()
        );

        $inventoryAccount = ChartOfAccount::query()
            ->where(
                'account_code',
                AccountingAccounts::INVENTORY
            )
            ->first();

        $equityAccount = ChartOfAccount::query()
            ->where(
                'account_code',
                AccountingAccounts::OPENING_BALANCE_EQUITY
            )
            ->first();

        $this->assertNotNull($inventoryAccount);
        $this->assertNotNull($equityAccount);

        $journalEntry = JournalEntry::query()
            ->where('reference_type', OpeningStock::class)
            ->where('reference_id', $openingStock->id)
            ->first();

        $this->assertNotNull($journalEntry);

        $this->assertEquals(
            JournalVoucherTypeEnum::OPENING_STOCK,
            $journalEntry->voucher_type
        );

        $journalEntry->load('lines');

        $this->assertCount(
            2,
            $journalEntry->lines
        );

        $inventoryLine = $journalEntry->lines->firstWhere(
            'chart_of_account_id',
            $inventoryAccount->id
        );

        $equityLine = $journalEntry->lines->firstWhere(
            'chart_of_account_id',
            $equityAccount->id
        );

        $this->assertNotNull($inventoryLine);
        $this->assertNotNull($equityLine);

        $this->assertEquals(
            1000,
            (float) $inventoryLine->debit
        );

        $this->assertEquals(
            1000,
            (float) $equityLine->credit
        );
    }

    public function test_cannot_post_opening_stock_twice(): void
    {
        $openingStock = $this->createOpeningStock();

        $this->postingService->post(
            $openingStock->fresh()
        );

        $this->expectException(
            BusinessException::class
        );

        $this->postingService->post(
            $openingStock->fresh()
        );
    }

    public function test_cannot_unpost_draft_opening_stock(): void
    {
        $openingStock = $this->createOpeningStock();

        $this->expectException(
            BusinessException::class
        );

        $this->postingService->unpost(
            $openingStock->fresh()
        );
    }

    public function test_unpost_reverses_inventory_and_accounting(): void
    {
        $openingStock = $this->createOpeningStock();

        $this->postingService->post(
            $openingStock->fresh()
        );

        $this->assertEquals(
            10,
            (float) ProductStock::query()
                ->where('product_id', $this->product->id)
                ->where('warehouse_id', $this->warehouse->id)
                ->firstOrFail()
                ->quantity
        );

        $journalEntry = JournalEntry::query()
            ->where('reference_type', OpeningStock::class)
            ->where('reference_id', $openingStock->id)
            ->where(
                'voucher_type',
                JournalVoucherTypeEnum::OPENING_STOCK->value
            )
            ->firstOrFail();

        $this->postingService->unpost(
            $openingStock->fresh()
        );

        $this->assertEquals(
            DocumentStatusEnum::DRAFT->value,
            $openingStock->fresh()->status
        );

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertTrue(
            $stock === null
            || (float) $stock->quantity === 0.0
        );

        $reversal = JournalEntry::query()
            ->where(
                'reversal_of_journal_entry_id',
                $journalEntry->id
            )
            ->first();

        $this->assertNotNull($reversal);

        $this->assertEquals(
            JournalEntryStatusEnum::POSTED,
            $reversal->status
        );

        $journalEntry->load('lines');
        $reversal->load('lines');

        $this->assertEquals(
            1000,
            (float) $journalEntry->lines->sum('debit')
        );

        $this->assertEquals(
            1000,
            (float) $reversal->lines->sum('credit')
        );

        $originalLedgerCount = StockLedger::query()
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
                InventoryTransactionTypeEnum::OPENING_STOCK->value
            )
            ->count();

        $this->assertEquals(
            1,
            $originalLedgerCount
        );

        $this->assertEquals(
            2,
            StockLedger::query()
                ->where(
                    'referenceable_type',
                    OpeningStock::class
                )
                ->where(
                    'referenceable_id',
                    $openingStock->id
                )
                ->count()
        );
    }

    public function test_unpost_fails_when_accounting_journal_is_missing(): void
    {
        $openingStock = $this->createOpeningStock();

        $this->postingService->post(
            $openingStock->fresh()
        );

        $journalEntry = JournalEntry::query()
            ->where('reference_type', OpeningStock::class)
            ->where('reference_id', $openingStock->id)
            ->where(
                'voucher_type',
                JournalVoucherTypeEnum::OPENING_STOCK->value
            )
            ->firstOrFail();

        $journalEntry->delete();

        $this->expectException(
            BusinessException::class
        );

        try {
            $this->postingService->unpost(
                $openingStock->fresh()
            );
        } finally {
            $stock = ProductStock::query()
                ->where('product_id', $this->product->id)
                ->where('warehouse_id', $this->warehouse->id)
                ->firstOrFail();

            $this->assertEquals(
                10,
                (float) $stock->quantity
            );

            $this->assertEquals(
                DocumentStatusEnum::CONFIRMED->value,
                $openingStock->fresh()->status
            );
        }
    }

    protected function createOpeningStock(): OpeningStock
    {
        return $this->openingStockService->create([
            'warehouse_id' => $this->warehouse->id,
            'opening_date' => now()->toDateString(),
            'remarks' => 'Opening stock posting test',

            'sources' => [
                [
                    'supplier_id' => null,
                    'bill_no' => 'OPEN-' . uniqid(),
                    'bill_date' => now()->toDateString(),
                    'remarks' => 'Test source',

                    'items' => [
                        [
                            'product_id' => $this->product->id,
                            'product_variant_id' => null,
                            'quantity' => 10,
                            'unit_cost' => 100,
                            'remarks' => 'Test item',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
