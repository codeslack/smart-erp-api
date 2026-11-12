<?php

namespace Tests\Feature\Purchase;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Services\PurchaseReturnService;
use App\Modules\Product\Models\Product;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Warehouse\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesProduct;
use Tests\Support\CreatesSupplier;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\Support\CreatesWarehouse;
use Tests\TestCase;

class PurchaseCurrencyPostingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;
    use CreatesSupplier;
    use CreatesWarehouse;
    use CreatesProduct;

    protected Tenant $tenant;
    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->user = $this->createTestUser();

        Sanctum::actingAs($this->user);
        app(TenantManager::class)->setTenant($this->tenant);
        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = $this->createSupplier();
        $this->warehouse = $this->createWarehouse();
        $this->product = $this->createProduct([
            'purchase_price' => 100,
            'selling_price' => 150,
        ]);
    }

    public function test_foreign_currency_purchase_converts_inventory_and_posts_base_and_foreign_amounts(): void
    {
        $purchase = $this->createForeignPurchase();
        $purchase = app(PurchaseService::class)->confirm($purchase);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();

        $this->assertEquals(8000, (float) $stock->average_cost);
        $this->assertEquals(5, (float) $stock->quantity);

        $entry = JournalEntry::query()
            ->where('voucher_type', JournalVoucherTypeEnum::PURCHASE->value)
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->with('lines.account')
            ->firstOrFail();

        $this->assertSame('USD', $entry->currency_code);
        $this->assertEquals(80, (float) $entry->exchange_rate);

        $lines = $entry->lines->keyBy(
            fn ($line) => $line->account->account_code
        );

        $inventory = $lines->get(AccountingAccounts::INVENTORY);
        $inputTax = $lines->get(AccountingAccounts::INPUT_TAX_RECEIVABLE);
        $payable = $lines->get(AccountingAccounts::ACCOUNTS_PAYABLE);

        $this->assertEquals(40000, (float) $inventory->debit);
        $this->assertEquals(500, (float) $inventory->foreign_debit);

        $this->assertEquals(4000, (float) $inputTax->debit);
        $this->assertEquals(50, (float) $inputTax->foreign_debit);

        $this->assertEquals(44000, (float) $payable->credit);
        $this->assertEquals(550, (float) $payable->foreign_credit);
    }

    public function test_purchase_return_inherits_currency_and_posts_base_and_foreign_amounts(): void
    {
        $purchase = $this->createForeignPurchase();
        $purchase = app(PurchaseService::class)->confirm($purchase);
        $purchaseItem = $purchase->items()->firstOrFail();

        $purchaseReturn = app(PurchaseReturnService::class)->create([
            'purchase_uuid' => $purchase->uuid,
            'return_date' => now()->toDateString(),
            'refund_type' => PurchaseReturnRefundType::CREDIT_NOTE->value,
            'items' => [[
                'purchase_item_uuid' => $purchaseItem->uuid,
                'returned_quantity' => 2,
            ]],
        ]);

        $purchaseReturn = app(PurchaseReturnService::class)->confirm(
            $purchaseReturn
        );

        $this->assertSame('USD', $purchaseReturn->currency_code);
        $this->assertEquals(80, (float) $purchaseReturn->exchange_rate);

        $entry = JournalEntry::query()
            ->where('voucher_type', JournalVoucherTypeEnum::PURCHASE_RETURN->value)
            ->where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $purchaseReturn->id)
            ->with('lines.account')
            ->firstOrFail();

        $this->assertSame('USD', $entry->currency_code);
        $this->assertEquals(80, (float) $entry->exchange_rate);

        $lines = $entry->lines->keyBy(
            fn ($line) => $line->account->account_code
        );

        $payable = $lines->get(AccountingAccounts::ACCOUNTS_PAYABLE);
        $inventory = $lines->get(AccountingAccounts::INVENTORY);
        $inputTax = $lines->get(AccountingAccounts::INPUT_TAX_RECEIVABLE);

        $this->assertEquals(17600, (float) $payable->debit);
        $this->assertEquals(220, (float) $payable->foreign_debit);

        $this->assertEquals(16000, (float) $inventory->credit);
        $this->assertEquals(200, (float) $inventory->foreign_credit);

        $this->assertEquals(1600, (float) $inputTax->credit);
        $this->assertEquals(20, (float) $inputTax->foreign_credit);

        $this->assertEquals(3, (float) ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail()
            ->quantity);
    }

    protected function createForeignPurchase(): Purchase
    {
        return app(PurchaseService::class)->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'currency_code' => 'USD',
            'exchange_rate' => 80,
            'items' => [[
                'product_uuid' => $this->product->uuid,
                'quantity' => 5,
                'unit_cost' => 100,
                'tax_rate' => 10,
            ]],
        ]);
    }
}
