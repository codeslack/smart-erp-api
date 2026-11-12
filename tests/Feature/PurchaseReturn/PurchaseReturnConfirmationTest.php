<?php

namespace Tests\Feature\PurchaseReturn;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class PurchaseReturnConfirmationTest extends TestCase
{
    use RefreshDatabase, CreatesTenant, CreatesUser;

    protected Tenant $tenant;
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Purchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);
        app(TenantManager::class)->setTenant($this->tenant);
        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Supplier Co', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Central Warehouse', 'code' => 'WH-CENTRAL', 'is_active' => true]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRD-CONF-01',
            'sku' => 'SKU-CONF-01',
            'name' => 'Confirmed Product',
            'slug' => 'confirmed-product',
            'product_type' => ProductTypeEnum::PRODUCT,
            'inventory_tracking_type' => InventoryTrackingTypeEnum::NONE,
            'is_active' => true,
            'purchase_price' => 100,
            'selling_price' => 150,
        ]);

        $svc = app(PurchaseService::class);
        $this->purchase = $svc->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_uuid' => $this->product->uuid, 'quantity' => 5, 'unit_cost' => 100]],
        ]);
        $this->purchase = $svc->confirm($this->purchase);
    }

    public function test_purchase_return_confirmation_reduces_inventory_and_posts_accounting(): void
    {
        $purchaseItem = $this->purchase->items->first();

        $createRes = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'return_reason' => 'Defective batch',
            'items' => [['purchase_item_uuid' => $purchaseItem->uuid, 'returned_quantity' => 2, 'unit_cost' => 100]],
        ]);
        $createRes->assertStatus(201);
        $returnUuid = $createRes->json('data.uuid');

        $confirmRes = $this->postJson("/api/purchase-returns/{$returnUuid}/confirm");
        $confirmRes->assertStatus(200)->assertJsonPath('data.status', 'confirmed');

        app(TenantManager::class)->setTenant($this->tenant);
        $returnId = PurchaseReturn::query()->where('uuid', $returnUuid)->value('id');

        $stockAfter = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(3, (float) $stockAfter->quantity);

        $this->assertDatabaseHas('stock_ledgers', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_out' => 2,
            'transaction_type' => InventoryTransactionTypeEnum::PURCHASE_RETURN->value,
        ]);

        $journalEntry = JournalEntry::query()
            ->where('voucher_type', JournalVoucherTypeEnum::PURCHASE_RETURN->value)
            ->where('reference_id', $returnId)
            ->first();
        $this->assertNotNull($journalEntry);

        $this->purchase->refresh();
        $this->assertEquals(300, (float) $this->purchase->due_amount);
        $this->assertEquals(200, (float) $this->purchase->returned_amount);
    }

    public function test_purchase_return_with_tax_creates_balanced_voucher(): void
    {
        $purchaseService = app(PurchaseService::class);
        $taxedPurchase = $purchaseService->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_uuid' => $this->product->uuid,
                'quantity' => 1,
                'unit_cost' => 100,
                'tax_rate' => 10,
            ]],
        ]);
        $taxedPurchase = $purchaseService->confirm($taxedPurchase);
        $purchaseItem = $taxedPurchase->items->first();

        $createRes = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $taxedPurchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'purchase_item_uuid' => $purchaseItem->uuid,
                'returned_quantity' => 1,
                'unit_cost' => 100,
                'tax_rate' => 10,
                'tax_amount' => 10,
            ]],
        ]);
        $createRes->assertStatus(201);
        $returnUuid = $createRes->json('data.uuid');

        $this->postJson("/api/purchase-returns/{$returnUuid}/confirm")->assertStatus(200);

        app(TenantManager::class)->setTenant($this->tenant);
        $returnId = PurchaseReturn::query()->where('uuid', $returnUuid)->value('id');

        $journalEntry = JournalEntry::query()
            ->where('voucher_type', JournalVoucherTypeEnum::PURCHASE_RETURN->value)
            ->where('reference_id', $returnId)
            ->with('lines')
            ->first();

        $this->assertNotNull($journalEntry);
        $totalDebit = $journalEntry->lines->sum('debit');
        $totalCredit = $journalEntry->lines->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(110, (float) $totalDebit);
    }

    public function test_purchase_return_with_bank_refund_debits_refund_account(): void
    {
        $purchaseItem = $this->purchase->items->first();
        $bankAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_code', AccountingAccounts::BANK)
            ->first();

        $createRes = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $this->purchase->uuid,
            'return_date' => now()->toDateString(),
            'refund_type' => PurchaseReturnRefundType::BANK_REFUND->value,
            'refund_account_uuid' => $bankAccount->uuid,
            'items' => [['purchase_item_uuid' => $purchaseItem->uuid, 'returned_quantity' => 1, 'unit_cost' => 100]],
        ]);
        $createRes->assertStatus(201);
        $returnUuid = $createRes->json('data.uuid');

        $this->postJson("/api/purchase-returns/{$returnUuid}/confirm")->assertStatus(200);

        app(TenantManager::class)->setTenant($this->tenant);
        $returnId = PurchaseReturn::query()->where('uuid', $returnUuid)->value('id');

        $journalEntry = JournalEntry::query()
            ->where('voucher_type', JournalVoucherTypeEnum::PURCHASE_RETURN->value)
            ->where('reference_id', $returnId)
            ->with('lines')
            ->first();

        $this->assertNotNull($journalEntry);
        $debitLine = $journalEntry->lines->firstWhere('debit', '>', 0);
        $this->assertEquals($bankAccount->id, $debitLine->chart_of_account_id);
    }

    public function test_foreign_currency_purchase_and_return_reconcile_base_inventory_and_gl(): void
    {
        $purchaseService = app(PurchaseService::class);

        $foreignPurchase = $purchaseService->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 83.25,
            'items' => [[
                'product_uuid' => $this->product->uuid,
                'quantity' => 1,
                'unit_cost' => 100,
            ]],
        ]);

        $foreignPurchase = $purchaseService->confirm($foreignPurchase);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertEqualsWithDelta(
            1470.8333,
            (float) $stock->average_cost,
            0.0001
        );

        $purchaseEntry = JournalEntry::query()
            ->with('lines.account')
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $foreignPurchase->id)
            ->firstOrFail();

        $this->assertSame('USD', $purchaseEntry->currency_code);
        $this->assertEquals(83.25, (float) $purchaseEntry->exchange_rate);

        $purchasePayable = $purchaseEntry->lines->first(
            fn ($line) => $line->account->account_code === AccountingAccounts::ACCOUNTS_PAYABLE
        );

        $this->assertNotNull($purchasePayable);
        $this->assertEquals(8325, (float) $purchasePayable->credit);
        $this->assertEquals(100, (float) $purchasePayable->foreign_credit);

        $purchaseItem = $foreignPurchase->items()->firstOrFail();

        $createResponse = $this->postJson('/api/purchase-returns', [
            'purchase_uuid' => $foreignPurchase->uuid,
            'return_date' => now()->toDateString(),
            'items' => [[
                'purchase_item_uuid' => $purchaseItem->uuid,
                'returned_quantity' => 1,
                'unit_cost' => 100,
            ]],
        ])->assertCreated();

        $returnUuid = $createResponse->json('data.uuid');

        $this->postJson("/api/purchase-returns/{$returnUuid}/confirm")
            ->assertOk();

        app(TenantManager::class)->setTenant($this->tenant);

        $purchaseReturn = PurchaseReturn::query()
            ->where('uuid', $returnUuid)
            ->firstOrFail();

        $this->assertSame('USD', $purchaseReturn->currency_code);
        $this->assertEquals(83.25, (float) $purchaseReturn->exchange_rate);

        $returnEntry = JournalEntry::query()
            ->with('lines.account')
            ->where('voucher_type', JournalVoucherTypeEnum::PURCHASE_RETURN->value)
            ->where('reference_id', $purchaseReturn->id)
            ->firstOrFail();

        $inventoryLedger = StockLedger::query()
            ->where('referenceable_type', PurchaseReturn::class)
            ->where('referenceable_id', $purchaseReturn->id)
            ->where('quantity_out', '>', 0)
            ->firstOrFail();

        $inventoryLine = $returnEntry->lines->first(
            fn ($line) => $line->account->account_code === AccountingAccounts::INVENTORY
        );

        $varianceLine = $returnEntry->lines->first(
            fn ($line) => $line->account->account_code === AccountingAccounts::PURCHASE_RETURN_VARIANCE
        );

        $this->assertNotNull($inventoryLine);
        $this->assertNotNull($varianceLine);

        $this->assertEquals(
            (float) $inventoryLedger->total_cost,
            (float) $inventoryLine->credit
        );

        $this->assertEqualsWithDelta(
            8325 - (float) $inventoryLedger->total_cost,
            (float) $varianceLine->credit,
            0.0001
        );

        $this->assertEquals(
            (float) $returnEntry->lines->sum('debit'),
            (float) $returnEntry->lines->sum('credit')
        );

        $this->assertEquals(100, (float) $inventoryLine->foreign_credit + (float) $varianceLine->foreign_credit);
    }

}
