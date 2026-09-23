<?php

namespace Tests\Feature\Customer;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Customer\Models\Customer;
use App\Modules\PaymentTerm\Models\PaymentTerm;
use App\Modules\Customer\Services\CustomerService;

class CustomerPaymentTermsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);
    }

    public function test_customer_can_be_created_with_payment_term_and_credit_settings(): void
    {
        $paymentTerm = PaymentTerm::query()->create([
            'code' => 'NET30',
            'name' => 'Net 30 Days',
            'due_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Test Customer',
            'code' => 'CUS-001',
            'payment_term_id' => $paymentTerm->id,
            'credit_days' => null,
            'credit_limit' => 50000,
            'credit_control' => 'warn',
            'is_active' => true,
        ]);

        $this->assertSame(
            $this->tenant->id,
            $customer->tenant_id
        );

        $this->assertSame(
            $paymentTerm->id,
            $customer->payment_term_id
        );

        $this->assertNull(
            $customer->credit_days
        );

        $this->assertEquals(
            50000,
            (float) $customer->credit_limit
        );

        $this->assertSame(
            'warn',
            $customer->credit_control
        );
    }

    public function test_customer_service_loads_payment_term(): void
    {
        $paymentTerm = PaymentTerm::query()->create([
            'code' => 'NET30',
            'name' => 'Net 30 Days',
            'due_days' => 30,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Test Customer',
            'code' => 'CUS-002',
            'payment_term_id' => $paymentTerm->id,
            'is_active' => true,
        ]);

        $customer = app(
            CustomerService::class
        )->findByUuid($customer->uuid);

        $this->assertNotNull($customer);

        $this->assertTrue(
            $customer->relationLoaded('paymentTerm')
        );

        $this->assertSame(
            'NET30',
            $customer->paymentTerm->code
        );

        $this->assertEquals(
            30,
            $customer->paymentTerm->due_days
        );
    }

    public function test_customer_can_update_credit_settings(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Test Customer',
            'code' => 'CUS-003',
            'is_active' => true,
        ]);

        $customerService = app(
            CustomerService::class
        );

        $customer = $customerService->update(
            $customer,
            [
                'credit_days' => 15,
                'credit_limit' => 25000,
                'credit_control' => 'block',
            ]
        );

        $this->assertSame(
            15,
            $customer->credit_days
        );

        $this->assertEquals(
            25000,
            (float) $customer->credit_limit
        );

        $this->assertSame(
            'block',
            $customer->credit_control
        );
    }

    public function test_customer_payment_term_must_belong_to_current_tenant(): void
    {
        $foreignTenant = Tenant::query()->create([
            'name' => 'Foreign Company',
            'code' => 'foreign-' . uniqid(),
            'slug' => 'foreign-' . uniqid(),
            'domain' => 'foreign-' . uniqid() . '.local',
            'business_type' => $this->tenant->business_type,
            'is_active' => true,
        ]);

        app(TenantManager::class)
            ->setTenant($foreignTenant);

        $foreignPaymentTerm = PaymentTerm::query()->create([
            'code' => 'FOREIGN30',
            'name' => 'Foreign Net 30',
            'due_days' => 30,
            'is_active' => true,
        ]);

        app(TenantManager::class)
            ->setTenant($this->tenant);

        $this->assertNull(
            PaymentTerm::query()
                ->where('id', $foreignPaymentTerm->id)
                ->first()
        );
    }

    public function test_customer_is_tenant_scoped(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Tenant Customer',
            'code' => 'CUS-004',
            'is_active' => true,
        ]);

        $this->assertNotNull(
            Customer::query()
                ->where('uuid', $customer->uuid)
                ->first()
        );

        $foreignTenant = Tenant::query()->create([
            'name' => 'Foreign Company',
            'code' => 'foreign-' . uniqid(),
            'slug' => 'foreign-' . uniqid(),
            'domain' => 'foreign-' . uniqid() . '.local',
            'business_type' => $this->tenant->business_type,
            'is_active' => true,
        ]);

        app(TenantManager::class)
            ->setTenant($foreignTenant);

        $this->assertNull(
            Customer::query()
                ->where('uuid', $customer->uuid)
                ->first()
        );
    }
}
