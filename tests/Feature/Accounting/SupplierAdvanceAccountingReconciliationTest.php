<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantManager;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Services\Contracts\PaymentServiceInterface;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseService;
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

class SupplierAdvanceAccountingReconciliationTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        app(TenantManager::class)->setTenant($this->tenant);

        $this->user = $this->createTestUser();
        Sanctum::actingAs($this->user);

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = $this->createSupplier();
        $this->warehouse = $this->createWarehouse();
    }

    public function test_supplier_advance_allocation_reconciles_purchase_due_and_gl_control_accounts(): void
    {
        $cashAccount = $this->account(AccountingAccounts::CASH);

        $advance = app(PaymentServiceInterface::class)->create([
            'payment_no' => 'SUP-ADV-RECON-001',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'purpose' => PaymentPurposeEnum::SUPPLIER_ADVANCE,
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $cashAccount->id,
            'amount' => 300,
            'payment_method' => PaymentMethodEnum::CASH,
        ]);

        $advance = app(PaymentServiceInterface::class)->confirm($advance);

        $product = $this->createProduct([
            'purchase_price' => 100,
            'selling_price' => 150,
        ]);

        $purchaseService = app(PurchaseService::class);
        $purchase = $purchaseService->create([
            'supplier_uuid' => $this->supplier->uuid,
            'warehouse_uuid' => $this->warehouse->uuid,
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_uuid' => $product->uuid,
                'quantity' => 10,
                'unit_cost' => 100,
            ]],
        ]);

        $purchase = $purchaseService->confirm($purchase)->fresh();

        $this->assertSame(
            PurchaseStatusEnum::PARTIALLY_PAID,
            $purchase->status,
        );
        $this->assertEquals(300, (float) $purchase->paid_amount);
        $this->assertEquals(700, (float) $purchase->due_amount);

        $allocation = AdvanceAllocation::query()
            ->where('source_id', $advance->id)
            ->where('target_type', Purchase::class)
            ->where('target_id', $purchase->id)
            ->firstOrFail();

        $this->assertEquals(300, (float) $allocation->allocated_amount);

        $payable = $this->account(AccountingAccounts::ACCOUNTS_PAYABLE);
        $supplierAdvances = $this->account(AccountingAccounts::SUPPLIER_ADVANCES);

        $this->assertEquals(700, (float) $payable->current_balance);
        $this->assertEquals(0, (float) $supplierAdvances->current_balance);

        $adjustment = JournalEntry::query()
            ->where('voucher_type', JournalVoucherTypeEnum::SUPPLIER_ADVANCE_ADJUSTMENT->value)
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->with('lines.account')
            ->firstOrFail();

        $this->assertEquals(
            300,
            (float) $adjustment->lines->sum('debit'),
        );
        $this->assertEquals(
            300,
            (float) $adjustment->lines->sum('credit'),
        );
        $this->assertTrue(
            $adjustment->lines->contains(
                fn ($line): bool =>
                    $line->account?->account_code === AccountingAccounts::ACCOUNTS_PAYABLE
                    && (float) $line->debit === 300.0
            )
        );
        $this->assertTrue(
            $adjustment->lines->contains(
                fn ($line): bool =>
                    $line->account?->account_code === AccountingAccounts::SUPPLIER_ADVANCES
                    && (float) $line->credit === 300.0
            )
        );
    }

    protected function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::query()
            ->where('account_code', $code)
            ->firstOrFail();
    }
}
