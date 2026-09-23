<?php

namespace Tests\Feature\PaymentTerm;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\PaymentTerm\Models\PaymentTerm;
use App\Modules\Tenant\Services\TenantSetupService;

class PaymentTermSetupTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    public function test_new_tenant_receives_default_payment_terms(): void
    {
        $tenant = $this->createTestTenant();

        app(TenantSetupService::class)
            ->setup($tenant);

        $paymentTerms = PaymentTerm::query()
            ->where('tenant_id', $tenant->id)
            ->get();

        $this->assertCount(
            6,
            $paymentTerms
        );

        $this->assertEqualsCanonicalizing(
            [
                'CASH',
                'NET7',
                'NET15',
                'NET30',
                'NET45',
                'NET60',
            ],
            $paymentTerms
                ->pluck('code')
                ->all()
        );
    }

    public function test_default_payment_terms_have_correct_due_days(): void
    {
        $tenant = $this->createTestTenant();

        app(TenantSetupService::class)
            ->setup($tenant);

        $expected = [
            'CASH'  => 0,
            'NET7'  => 7,
            'NET15' => 15,
            'NET30' => 30,
            'NET45' => 45,
            'NET60' => 60,
        ];

        foreach ($expected as $code => $dueDays) {
            $paymentTerm = PaymentTerm::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', $code)
                ->first();

            $this->assertNotNull(
                $paymentTerm,
                "Payment term [{$code}] was not created."
            );

            $this->assertSame(
                $dueDays,
                $paymentTerm->due_days
            );

            $this->assertTrue(
                $paymentTerm->is_active
            );
        }
    }

    public function test_payment_term_setup_is_idempotent(): void
    {
        $tenant = $this->createTestTenant();

        $setup = app(TenantSetupService::class);

        $setup->setup($tenant);

        $this->assertSame(
            6,
            PaymentTerm::query()
                ->where('tenant_id', $tenant->id)
                ->count()
        );

        $setup->setup($tenant);

        $this->assertSame(
            6,
            PaymentTerm::query()
                ->where('tenant_id', $tenant->id)
                ->count()
        );

        $this->assertSame(
            1,
            PaymentTerm::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', 'CASH')
                ->count()
        );

        $this->assertSame(
            1,
            PaymentTerm::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', 'NET30')
                ->count()
        );
    }
}
