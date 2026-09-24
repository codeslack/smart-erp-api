<?php

namespace Tests\Feature\PaymentTerm;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;

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

    public function test_default_payment_terms_have_correct_values(): void
    {
        $tenant = $this->createTestTenant();

        app(TenantSetupService::class)
            ->setup($tenant);

        $expected = [
            'CASH' => [
                'due_days' => 0,
                'discount_days' => 0,
                'discount_percent' => '0.0000',
                'grace_days' => 0,
            ],

            'NET7' => [
                'due_days' => 7,
                'discount_days' => 0,
                'discount_percent' => '0.0000',
                'grace_days' => 0,
            ],

            'NET15' => [
                'due_days' => 15,
                'discount_days' => 0,
                'discount_percent' => '0.0000',
                'grace_days' => 0,
            ],

            'NET30' => [
                'due_days' => 30,
                'discount_days' => 0,
                'discount_percent' => '0.0000',
                'grace_days' => 0,
            ],

            'NET45' => [
                'due_days' => 45,
                'discount_days' => 0,
                'discount_percent' => '0.0000',
                'grace_days' => 0,
            ],

            'NET60' => [
                'due_days' => 60,
                'discount_days' => 0,
                'discount_percent' => '0.0000',
                'grace_days' => 0,
            ],
        ];

        foreach ($expected as $code => $values) {

            $paymentTerm = PaymentTerm::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', $code)
                ->first();

            $this->assertNotNull(
                $paymentTerm,
                "Payment term [{$code}] was not created."
            );

            $this->assertSame(
                $values['due_days'],
                $paymentTerm->due_days
            );

            $this->assertSame(
                $values['discount_days'],
                $paymentTerm->discount_days
            );

            $this->assertSame(
                $values['discount_percent'],
                $paymentTerm->discount_percent
            );

            $this->assertSame(
                $values['grace_days'],
                $paymentTerm->grace_days
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