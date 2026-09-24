<?php

namespace Tests\Feature\PaymentTerm;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use Laravel\Sanctum\Sanctum;

use App\Core\Tenant\TenantManager;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\PaymentTerm\Models\PaymentTerm;

class PaymentTermApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TEST-USER',
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
    }

    protected function validPayload(
        array $overrides = []
    ): array {
        return array_merge([
            'code' => 'NET30-' . uniqid(),
            'name' => 'Net 30 Days ' . uniqid(),
            'due_days' => 30,
            'discount_days' => 10,
            'discount_percent' => 2,
            'grace_days' => 5,
            'description' => 'Payment due within 30 days.',
            'is_active' => true,
        ], $overrides);
    }

    public function test_payment_term_can_be_created(): void
    {
        $payload = $this->validPayload();

        $response = $this->postJson(
            route('payment-terms.store'),
            $payload
        );

        $response->assertCreated();

        $paymentTerm = PaymentTerm::query()
            ->where('code', $payload['code'])
            ->firstOrFail();

        $this->assertSame(
            $payload['name'],
            $paymentTerm->name
        );

        $this->assertSame(
            $payload['due_days'],
            $paymentTerm->due_days
        );

        $this->assertSame(
            $payload['discount_days'],
            $paymentTerm->discount_days
        );

        $this->assertSame(
            '2.0000',
            $paymentTerm->discount_percent
        );

        $this->assertSame(
            $payload['grace_days'],
            $paymentTerm->grace_days
        );

        $this->assertTrue(
            $paymentTerm->is_active
        );

        $response->assertJsonPath(
            'data.uuid',
            $paymentTerm->uuid
        );

        $response->assertJsonPath(
            'data.code',
            $payload['code']
        );
    }

    public function test_payment_terms_can_be_listed(): void
    {
        PaymentTerm::query()->create(
            $this->validPayload([
                'code' => 'NET30',
                'name' => 'Net 30 Days',
            ])
        );

        PaymentTerm::query()->create(
            $this->validPayload([
                'code' => 'NET60',
                'name' => 'Net 60 Days',
                'due_days' => 60,
                'discount_days' => 0,
                'discount_percent' => 0,
                'grace_days' => 0,
            ])
        );

        $response = $this->getJson(
            route('payment-terms.index')
        );

        $response->assertSuccessful();

        $response->assertJsonCount(
            2,
            'data'
        );

        $response->assertJsonFragment([
            'code' => 'NET30',
            'name' => 'Net 30 Days',
        ]);

        $response->assertJsonFragment([
            'code' => 'NET60',
            'name' => 'Net 60 Days',
        ]);
    }

    public function test_payment_term_can_be_shown(): void
    {
        $paymentTerm = PaymentTerm::query()->create(
            $this->validPayload()
        );

        $response = $this->getJson(
            route('payment-terms.show', $paymentTerm)
        );

        $response->assertSuccessful();

        $response->assertJsonPath(
            'data.uuid',
            $paymentTerm->uuid
        );

        $response->assertJsonPath(
            'data.code',
            $paymentTerm->code
        );

        $response->assertJsonPath(
            'data.due_days',
            $paymentTerm->due_days
        );

        $response->assertJsonPath(
            'data.discount_days',
            $paymentTerm->discount_days
        );

        $response->assertJsonPath(
            'data.discount_percent',
            '2.0000'
        );

        $response->assertJsonPath(
            'data.grace_days',
            $paymentTerm->grace_days
        );
    }

    public function test_payment_term_can_be_updated(): void
    {
        $paymentTerm = PaymentTerm::query()->create(
            $this->validPayload([
                'code' => 'NET30',
                'name' => 'Net 30 Days',
                'discount_days' => 0,
                'discount_percent' => 0,
                'grace_days' => 0,
            ])
        );

        $response = $this->putJson(
            route('payment-terms.update', $paymentTerm),
            [
                'name' => 'Net 45 Days',
                'due_days' => 45,
                'discount_days' => 10,
                'discount_percent' => 2.5,
                'grace_days' => 7,
            ]
        );

        $response->assertSuccessful();

        $paymentTerm->refresh();

        $this->assertSame(
            'Net 45 Days',
            $paymentTerm->name
        );

        $this->assertSame(
            45,
            $paymentTerm->due_days
        );

        $this->assertSame(
            10,
            $paymentTerm->discount_days
        );

        $this->assertSame(
            '2.5000',
            $paymentTerm->discount_percent
        );

        $this->assertSame(
            7,
            $paymentTerm->grace_days
        );

        $response->assertJsonPath(
            'data.uuid',
            $paymentTerm->uuid
        );
    }

    public function test_discount_days_cannot_exceed_due_days(): void
    {
        $response = $this->postJson(
            route('payment-terms.store'),
            $this->validPayload([
                'due_days' => 10,
                'discount_days' => 11,
            ])
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'discount_days',
        ]);

        $this->assertDatabaseMissing(
            'payment_terms',
            [
                'code' => $response->json('data.code'),
            ]
        );
    }

    public function test_discount_percent_cannot_exceed_100(): void
    {
        $response = $this->postJson(
            route('payment-terms.store'),
            $this->validPayload([
                'discount_percent' => 100.01,
            ])
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'discount_percent',
        ]);
    }

    public function test_duplicate_code_is_rejected_within_tenant(): void
    {
        $paymentTerm = PaymentTerm::query()->create(
            $this->validPayload([
                'code' => 'NET30',
                'name' => 'Net 30 Days',
            ])
        );

        $response = $this->postJson(
            route('payment-terms.store'),
            $this->validPayload([
                'code' => $paymentTerm->code,
                'name' => 'Another Payment Term',
            ])
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'code',
        ]);
    }

    public function test_duplicate_name_is_rejected_within_tenant(): void
    {
        $paymentTerm = PaymentTerm::query()->create(
            $this->validPayload([
                'code' => 'NET30',
                'name' => 'Net 30 Days',
            ])
        );

        $response = $this->postJson(
            route('payment-terms.store'),
            $this->validPayload([
                'code' => 'NET30-SECOND',
                'name' => $paymentTerm->name,
            ])
        );

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors([
            'name',
        ]);
    }

    public function test_payment_term_can_be_deleted(): void
    {
        $paymentTerm = PaymentTerm::query()->create(
            $this->validPayload()
        );

        $response = $this->deleteJson(
            route('payment-terms.destroy', $paymentTerm)
        );

        $response->assertSuccessful();

        $this->assertSoftDeleted(
            'payment_terms',
            [
                'id' => $paymentTerm->id,
            ]
        );
    }
}