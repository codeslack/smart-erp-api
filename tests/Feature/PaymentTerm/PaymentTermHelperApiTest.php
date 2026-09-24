<?php

namespace Tests\Feature\PaymentTerm;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesUser;
use Tests\Support\CreatesTenant;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\PaymentTerm\Models\PaymentTerm;
use App\Modules\User\Models\User;

class PaymentTermHelperApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        $this->user = $this->createTestUser();
    }

    protected function authenticate(): void
    {
        $user = $this->tenant->users()->first();

        $this->actingAs(
            $user,
            'sanctum'
        );
    }

    public function test_payment_term_can_be_created(): void
    {
        $this->authenticate();

        $response = $this->postJson(
            '/api/payment-terms',
            [
                'code' => '2/10NET30',
                'name' => '2/10 Net 30',
                'due_days' => 30,
                'discount_days' => 10,
                'discount_percent' => 2,
                'grace_days' => 5,
                'description' => '2% discount within 10 days.',
                'is_active' => true,
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                '2/10NET30'
            )
            ->assertJsonPath(
                'data.name',
                '2/10 Net 30'
            )
            ->assertJsonPath(
                'data.due_days',
                30
            )
            ->assertJsonPath(
                'data.discount_days',
                10
            )
            ->assertJsonPath(
                'data.discount_percent',
                '2.0000'
            )
            ->assertJsonPath(
                'data.grace_days',
                5
            );

        $this->assertDatabaseHas(
            'payment_terms',
            [
                'tenant_id' => $this->tenant->id,
                'code' => '2/10NET30',
                'due_days' => 30,
                'discount_days' => 10,
                'discount_percent' => '2.0000',
                'grace_days' => 5,
            ]
        );
    }

    public function test_payment_terms_can_be_listed(): void
    {
        $this->authenticate();

        PaymentTerm::query()->create([
            'code' => 'NET30',
            'name' => 'Net 30 Days',
            'due_days' => 30,
            'discount_days' => 0,
            'discount_percent' => 0,
            'grace_days' => 0,
            'is_active' => true,
        ]);

        $response = $this->getJson(
            '/api/payment-terms'
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.code',
                'NET30'
            )
            ->assertJsonPath(
                'data.0.due_days',
                30
            );
    }

    public function test_payment_term_can_be_shown(): void
    {
        $this->authenticate();

        $paymentTerm = PaymentTerm::query()->create([
            'code' => 'NET30',
            'name' => 'Net 30 Days',
            'due_days' => 30,
            'discount_days' => 10,
            'discount_percent' => 2,
            'grace_days' => 5,
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/payment-terms/{$paymentTerm->uuid}"
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.uuid',
                $paymentTerm->uuid
            )
            ->assertJsonPath(
                'data.discount_days',
                10
            )
            ->assertJsonPath(
                'data.discount_percent',
                '2.0000'
            )
            ->assertJsonPath(
                'data.grace_days',
                5
            );
    }

    public function test_payment_term_can_be_updated(): void
    {
        $this->authenticate();

        $paymentTerm = PaymentTerm::query()->create([
            'code' => 'NET30',
            'name' => 'Net 30 Days',
            'due_days' => 30,
            'discount_days' => 0,
            'discount_percent' => 0,
            'grace_days' => 0,
            'is_active' => true,
        ]);

        $response = $this->patchJson(
            "/api/payment-terms/{$paymentTerm->uuid}",
            [
                'discount_days' => 10,
                'discount_percent' => 2,
                'grace_days' => 5,
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.discount_days',
                10
            )
            ->assertJsonPath(
                'data.discount_percent',
                '2.0000'
            )
            ->assertJsonPath(
                'data.grace_days',
                5
            );
    }

    public function test_discount_days_cannot_exceed_due_days(): void
    {
        $this->authenticate();

        $response = $this->postJson(
            '/api/payment-terms',
            [
                'code' => 'INVALID',
                'name' => 'Invalid Payment Term',
                'due_days' => 30,
                'discount_days' => 31,
                'discount_percent' => 2,
                'grace_days' => 0,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                ['discount_days']
            );
    }

    public function test_discount_percent_cannot_exceed_100(): void
    {
        $this->authenticate();

        $response = $this->postJson(
            '/api/payment-terms',
            [
                'code' => 'INVALID',
                'name' => 'Invalid Payment Term',
                'due_days' => 30,
                'discount_days' => 10,
                'discount_percent' => 101,
                'grace_days' => 0,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                ['discount_percent']
            );
    }

    public function test_duplicate_code_is_rejected_within_tenant(): void
    {
        $this->authenticate();

        PaymentTerm::query()->create([
            'code' => 'NET30',
            'name' => 'Net 30 Days',
            'due_days' => 30,
            'is_active' => true,
        ]);

        $response = $this->postJson(
            '/api/payment-terms',
            [
                'code' => 'NET30',
                'name' => 'Another Net 30',
                'due_days' => 30,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                ['code']
            );
    }

    public function test_duplicate_name_is_rejected_within_tenant(): void
    {
        $this->authenticate();

        PaymentTerm::query()->create([
            'code' => 'NET30',
            'name' => 'Net 30 Days',
            'due_days' => 30,
            'is_active' => true,
        ]);

        $response = $this->postJson(
            '/api/payment-terms',
            [
                'code' => 'NET30B',
                'name' => 'Net 30 Days',
                'due_days' => 30,
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                ['name']
            );
    }

    public function test_payment_term_can_be_deleted(): void
    {
        $this->authenticate();

        $paymentTerm = PaymentTerm::query()->create([
            'code' => 'NET30',
            'name' => 'Net 30 Days',
            'due_days' => 30,
            'is_active' => true,
        ]);

        $response = $this->deleteJson(
            "/api/payment-terms/{$paymentTerm->uuid}"
        );

        $response
            ->assertOk();

        $this->assertSoftDeleted(
            'payment_terms',
            [
                'id' => $paymentTerm->id,
            ]
        );
    }
}