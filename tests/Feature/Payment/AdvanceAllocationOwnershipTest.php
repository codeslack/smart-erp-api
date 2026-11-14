<?php

namespace Tests\Feature\Payment;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

use App\Core\Tenant\TenantManager;
use App\Core\Exceptions\BusinessException;

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
use App\Modules\Payment\Enums\PaymentStatusEnum;
use App\Modules\AdvanceAllocation\Models\AdvanceAllocation;
use App\Modules\AdvanceAllocation\Services\AdvanceAllocationService;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Services\AccountingSetupService;

class AdvanceAllocationOwnershipTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;
    protected User $user;
    protected Customer $customer;
    protected Customer $otherCustomer;
    protected Supplier $supplier;
    protected Supplier $otherSupplier;
    protected Warehouse $warehouse;
    protected ChartOfAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        app(TenantManager::class)->setTenant($this->tenant);

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'ADV-OWNER-USER',
            'tenant_id' => $this->tenant->id,
            'name' => 'Advance Owner Test',
            'email' => 'advance-owner-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        app(AccountingSetupService::class)->setup($this->tenant);

        $this->customer = Customer::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Customer A',
            'code' => 'ADV-CUST-A',
            'is_active' => true,
        ]);

        $this->otherCustomer = Customer::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Customer B',
            'code' => 'ADV-CUST-B',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier A',
            'code' => 'ADV-SUP-A',
            'is_active' => true,
        ]);

        $this->otherSupplier = Supplier::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Supplier B',
            'code' => 'ADV-SUP-B',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Advance Test Warehouse',
            'code' => 'ADV-WH',
            'is_active' => true,
        ]);

        $this->cashAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('account_code', AccountingAccounts::CASH)
            ->firstOrFail();
    }

    public function test_customer_advance_cannot_be_allocated_to_another_customers_sale(): void
    {
        $advance = Payment::query()->create([
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'ADV-CUST-OWNER-001',
            'payment_type' => PaymentTypeEnum::RECEIPT,
            'purpose' => PaymentPurposeEnum::CUSTOMER_ADVANCE,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::CUSTOMER,
            'party_id' => $this->otherCustomer->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 100,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        $sale = Sale::query()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'sale_no' => 'ADV-CUST-SALE-001',
            'sale_date' => now()->toDateString(),
            'grand_total' => 100,
            'paid_amount' => 0,
            'due_amount' => 100,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        try {
            app(AdvanceAllocationService::class)
                ->allocateCustomerAdvance($advance, $sale, 50);

            $this->fail('A customer advance must not be allocated to another customer.');
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Customer advance does not belong to the selected customer.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('advance_allocations', 0);
    }

    public function test_supplier_advance_cannot_be_allocated_to_another_suppliers_purchase(): void
    {
        $advance = Payment::query()->create([
            'tenant_id' => $this->tenant->id,
            'payment_no' => 'ADV-SUP-OWNER-001',
            'payment_type' => PaymentTypeEnum::PAYMENT,
            'purpose' => PaymentPurposeEnum::SUPPLIER_ADVANCE,
            'payment_date' => now()->toDateString(),
            'party_type' => PaymentPartyTypeEnum::SUPPLIER,
            'party_id' => $this->otherSupplier->id,
            'account_id' => $this->cashAccount->id,
            'amount' => 100,
            'payment_method' => PaymentMethodEnum::CASH,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        $purchase = Purchase::query()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_no' => 'ADV-SUP-PUR-001',
            'purchase_date' => now()->toDateString(),
            'grand_total' => 100,
            'paid_amount' => 0,
            'due_amount' => 100,
            'status' => PaymentStatusEnum::CONFIRMED->value,
        ]);

        try {
            app(AdvanceAllocationService::class)
                ->allocateSupplierAdvance($advance, $purchase, 50);

            $this->fail('A supplier advance must not be allocated to another supplier.');
        } catch (BusinessException $exception) {
            $this->assertSame(
                'Supplier advance does not belong to the selected supplier.',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('advance_allocations', 0);
    }
}
