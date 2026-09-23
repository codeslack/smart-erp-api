<?php

namespace Tests\Feature\Supplier;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\PaymentTerm\Models\PaymentTerm;
use App\Modules\Supplier\Services\SupplierService;

class SupplierPaymentTermsTest extends TestCase
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
            $this->tenant->id,
            $supplier->tenant_id
        );

        $this->assertSame(
            $paymentTerm->id,
            $supplier->payment_term_id
        );

        $this->assertNull(
            $supplier->credit_days
        );

        $this->assertEquals(
            75000,
            (float) $supplier->credit_limit
        );

        $this->assertSame(
            'warn',
            $supplier->credit_control
        );
    }

    public function test_supplier_service_loads_payment_term(): void
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
            SupplierService::class
        )->findByUuid($supplier->uuid);

        $this->assertNotNull($supplier);

        $this->assertTrue(
            $supplier->relationLoaded('paymentTerm')
        );

        $this->assertSame(
            'NET60',
            $supplier->paymentTerm->code
        );

        $this->assertEquals(
            60,
            $supplier->paymentTerm->due_days
        );
    }

    public function test_supplier_can_update_credit_settings(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Test Supplier',
            'code' => 'SUP-003',
            'is_active' => true,
        ]);

        $supplierService = app(
            SupplierService::class
        );

        $supplier = $supplierService->update(
            $supplier,
            [
                'credit_days' => 30,
                'credit_limit' => 100000,
                'credit_control' => 'block',
            ]
        );

        $this->assertSame(
            30,
            $supplier->credit_days
        );

        $this->assertEquals(
            100000,
            (float) $supplier->credit_limit
        );

        $this->assertSame(
            'block',
            $supplier->credit_control
        );
    }

    public function test_supplier_is_tenant_scoped(): void
    {
        $supplier = Supplier::query()->create([
            'name' => 'Tenant Supplier',
            'code' => 'SUP-004',
            'is_active' => true,
        ]);

        $this->assertNotNull(
            Supplier::query()
                ->where('uuid', $supplier->uuid)
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
            Supplier::query()
                ->where('uuid', $supplier->uuid)
                ->first()
        );
    }
}
