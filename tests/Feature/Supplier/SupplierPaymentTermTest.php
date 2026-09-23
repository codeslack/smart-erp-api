<?php

namespace Tests\Feature\Supplier;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\PaymentTerm\Models\PaymentTerm;

use Tests\Support\CreatesTenant;

class SupplierPaymentTermTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
    }

    public function test_supplier_can_be_created_with_payment_term_and_credit_settings(): void
    {
        $paymentTerm = PaymentTerm::query()->create([
            'code' => 'NET60',
            'name' => 'Net 60 Days',
            'due_days' => 60,
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Test Supplier',
            'code' => 'SUP-001',
            'payment_term_id' => $paymentTerm->id,
            'credit_days' => null,
            'credit_limit' => 75000,
            'credit_control' => 'warn',
            'is_active' => true,
        ]);

        $this->assertSame(
            $paymentTerm->id,
            $supplier->payment_term_id
        );

        $this->assertNull($supplier->credit_days);

        $this->assertSame(
            '75000.0000',
            $supplier->credit_limit
        );

        $this->assertSame(
            'warn',
            $supplier->credit_control
        );
    }

    public function test_supplier_payment_term_relationship_is_loaded(): void
    {
        $paymentTerm = PaymentTerm::query()->create([
            'code' => 'NET60',
            'name' => 'Net 60 Days',
            'due_days' => 60,
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Test Supplier',
            'code' => 'SUP-002',
            'payment_term_id' => $paymentTerm->id,
            'is_active' => true,
        ]);

        $supplier = app(
            \App\Modules\Supplier\Services\SupplierService::class
        )->findByUuid($supplier->uuid);

        $this->assertTrue(
            $supplier->relationLoaded('paymentTerm')
        );

        $this->assertSame(
            'NET60',
            $supplier->paymentTerm->code
        );
    }

    public function test_supplier_can_update_credit_settings(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Test Supplier',
            'code' => 'SUP-003',
            'is_active' => true,
        ]);

        $supplier->update([
            'credit_days' => 30,
            'credit_limit' => 100000,
            'credit_control' => 'block',
        ]);

        $supplier->refresh();

        $this->assertSame(30, $supplier->credit_days);
        $this->assertSame('100000.0000', $supplier->credit_limit);
        $this->assertSame('block', $supplier->credit_control);
    }
}