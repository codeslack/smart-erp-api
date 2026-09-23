<?php

namespace Tests\Feature\Customer;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Customer\Models\Customer;
use App\Modules\PaymentTerm\Models\PaymentTerm;

use Tests\Support\CreatesTenant;

class CustomerPaymentTermTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
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
            $paymentTerm->id,
            $customer->payment_term_id
        );

        $this->assertNull($customer->credit_days);

        $this->assertSame(
            '50000.0000',
            $customer->credit_limit
        );

        $this->assertSame(
            'warn',
            $customer->credit_control
        );
    }

    public function test_customer_payment_term_relationship_is_loaded(): void
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
            \App\Modules\Customer\Services\CustomerService::class
        )->findByUuid($customer->uuid);

        $this->assertTrue(
            $customer->relationLoaded('paymentTerm')
        );

        $this->assertSame(
            'NET30',
            $customer->paymentTerm->code
        );
    }

    public function test_customer_can_update_credit_settings(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Test Customer',
            'code' => 'CUS-003',
            'is_active' => true,
        ]);

        $customer->update([
            'credit_days' => 15,
            'credit_limit' => 25000,
            'credit_control' => 'block',
        ]);

        $customer->refresh();

        $this->assertSame(15, $customer->credit_days);
        $this->assertSame('25000.0000', $customer->credit_limit);
        $this->assertSame('block', $customer->credit_control);
    }
}
