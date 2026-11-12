<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Warehouse\Models\Warehouse;

use App\Modules\Sales\Models\Sale;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Enums\PaymentTypeEnum;
use App\Modules\Payment\Enums\PaymentPurposeEnum;
use App\Modules\Payment\Enums\PaymentPartyTypeEnum;
use App\Modules\Payment\Enums\PaymentMethodEnum;
use App\Modules\Payment\Services\Contracts\PaymentPostingServiceInterface;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Accounting\Services\Postings\SalesPostingService;
use App\Modules\Accounting\Services\Postings\PurchasePostingService;
use App\Modules\Accounting\Enums\AccountingAccounts;

class AccountingSubledgerReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected ChartOfAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        app(TenantManager::class)->setTenant($this->tenant);

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'RECON-USER',
            'tenant_id' => $this->tenant->id,
            'name' => 'Reconciliation Admin',
            'email' => 'recon-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->customer = Customer::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Reconciliation Customer',
            'code' => 'RECON-CUST',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Reconciliation Supplier',
            'code' => 'RECON-SUP',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Reconciliation Warehouse',
            'code' => 'RECON-WH',
            'is_active' => true,
        ]);

        $this->cashAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_code', AccountingAccounts::CASH)
            ->firstOrFail();
    }

    public function test_customer_and_supplier_subledgers_reconcile_to_control_accounts(): void
    {
        $sale = Sale::query()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_no' => 'RECON-SALE-001',
            'sale_date' => now()->toDateString(),
            'currency_code' => 'INR',
            'exchange_rate' => 1,
            'subtotal' => 1000,
            'grand_total' => 1000,
            'paid_amount' => 0,
            'due_amount' => 1000,
            'status' => 'confirmed',
        ]);

        app(SalesPostingService::class)->post($sale);

        $receipt = Payment::query()->create([
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RECON-REC-001',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->customer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 400,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => 'draft',
        ]);

        $receipt->allocations()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'document_type' => 'sales_invoice',
            'document_id' => $sale->id,
            'allocated_amount' => 400,
        ]);

        app(PaymentPostingServiceInterface::class)->confirm($receipt);

        $sale->refresh();

        $receivableAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_code', AccountingAccounts::ACCOUNTS_RECEIVABLE)
            ->firstOrFail();

        $receivableBalance = $receivableAccount->ledgers()
            ->latest('id')
            ->value('running_balance');

        $this->assertEquals(600, (float) $sale->due_amount);
        $this->assertEquals(600, (float) $receivableBalance);

        $purchase = Purchase::query()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_no' => 'RECON-PUR-001',
            'purchase_date' => now()->toDateString(),
            'currency_code' => 'INR',
            'exchange_rate' => 1,
            'subtotal' => 800,
            'grand_total' => 800,
            'paid_amount' => 0,
            'due_amount' => 800,
            'status' => 'confirmed',
        ]);

        app(PurchasePostingService::class)->post($purchase);

        $payment = Payment::query()->create([
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'RECON-PAY-001',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'purpose' => PaymentPurposeEnum::SUPPLIER_PAYMENT,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->supplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 300,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => 'draft',
        ]);

        $payment->allocations()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->id,
            'document_type' => 'purchase_bill',
            'document_id' => $purchase->id,
            'allocated_amount' => 300,
        ]);

        app(PaymentPostingServiceInterface::class)->confirm($payment);

        $purchase->refresh();

        $payableAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_code', AccountingAccounts::ACCOUNTS_PAYABLE)
            ->firstOrFail();

        $payableBalance = $payableAccount->ledgers()
            ->latest('id')
            ->value('running_balance');

        $this->assertEquals(500, (float) $purchase->due_amount);
        $this->assertEquals(500, (float) $payableBalance);
    }
}
