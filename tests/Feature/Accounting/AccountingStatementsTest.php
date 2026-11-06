<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use App\Core\Tenant\TenantManager;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Customer\Models\Customer;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Sales\Models\Sale;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Enums\PaymentStatusEnum;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Payment\Enums\PaymentPurposeEnum;

class AccountingStatementsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;
    protected User $user;
    protected Supplier $supplier;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        app(TenantManager::class)->setTenant($this->tenant);

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TEST-USER',
            'tenant_id' => $this->tenant->id,
            'name' => 'Report Admin',
            'email' => 'stmts-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
        app(AccountingSetupService::class)->setup($this->tenant);

        $this->supplier = Supplier::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Acme Supplies',
            'code' => 'SUP-001',
            'is_active' => true,
        ]);

        $this->customer = Customer::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Global Retail',
            'code' => 'CUST-001',
            'is_active' => true,
        ]);
    }

    public function test_supplier_statement_resolves_uuid_and_calculates_balance(): void
    {
        Purchase::query()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'purchase_no' => 'PUR-001',
            'purchase_date' => now()->toDateString(),
            'currency_code' => 'INR',
            'subtotal' => 5000,
            'grand_total' => 5000,
            'due_amount' => 5000,
            'paid_amount' => 0,
            'status' => 'confirmed',
        ]);

        $cashAccount = ChartOfAccount::query()
            ->where('account_code', AccountingAccounts::CASH)
            ->firstOrFail();

        Payment::query()->create([
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'PAY-001',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT,
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $cashAccount->id,
            'amount' => 2000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        $response = $this->getJson("/api/accounting/supplier-statements/{$this->supplier->uuid}");

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.outstanding_balance', 3000);

        $this->assertCount(2, $response->json('data.transactions'));
    }

    public function test_customer_statement_resolves_uuid_and_calculates_balance(): void
    {
        Sale::query()->create([
            'tenant_id' => $this->tenant->id,
            'warehouse_id' => $this->customer->id,
            'customer_id' => $this->customer->id,
            'sale_no' => 'INV-001',
            'sale_date' => now()->toDateString(),
            'subtotal' => 8000,
            'grand_total' => 8000,
            'due_amount' => 8000,
            'paid_amount' => 0,
            'status' => 'confirmed',
        ]);

        $cashAccount = ChartOfAccount::query()
            ->where('account_code', AccountingAccounts::CASH)
            ->firstOrFail();

        Payment::query()->create([
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'REC-001',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT,
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'account_id' => $cashAccount->id,
            'amount' => 3000,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        $response = $this->getJson("/api/accounting/customer-statements/{$this->customer->uuid}");

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.outstanding_balance', 5000);

        $this->assertCount(2, $response->json('data.transactions'));
    }

    public function test_aging_and_summary_reports(): void
    {
        $supplierAging = $this->getJson('/api/accounting/supplier-aging');
        $supplierAging->assertSuccessful()->assertJsonPath('success', true);

        $customerAging = $this->getJson('/api/accounting/customer-aging');
        $customerAging->assertSuccessful()->assertJsonPath('success', true);

        $payableSummary = $this->getJson('/api/accounting/payable-summary');
        $payableSummary->assertSuccessful()->assertJsonPath('success', true);

        $receivableSummary = $this->getJson('/api/accounting/receivable-summary');
        $receivableSummary->assertSuccessful()->assertJsonPath('success', true);
    }
}
