<?php

namespace Tests\Feature\Tax;

use Tests\TestCase;

use Laravel\Sanctum\Sanctum;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesUser;
use Tests\Support\CreatesTenant;

use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;

use App\Modules\Tax\Models\TaxRegistration;

class TaxRegistrationApiTest extends TestCase
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

        Sanctum::actingAs($this->user);
    }

    public function test_registration_can_be_created(): void
    {
        $response = $this->postJson(
            '/api/tax-registrations',
            $this->registrationPayload()
        );

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Tax registration created successfully.',
            ]);

        $this->assertDatabaseHas('tax_registrations', [
            'tenant_id' => $this->tenant->id,
            'registration_type' => 'GSTIN',
            'registration_number' => '22AAAAA0000A1Z5',
        ]);
    }

    public function test_registration_can_be_listed(): void
    {
        $this->createRegistration();

        $response = $this->getJson(
            '/api/tax-registrations'
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tax registrations retrieved successfully.',
            ]);
    }

    public function test_registration_can_be_retrieved(): void
    {
        $registration = $this->createRegistration();

        $response = $this->getJson(
            "/api/tax-registrations/{$registration->uuid}"
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tax registration retrieved successfully.',
            ]);
    }

    public function test_registration_can_be_updated(): void
    {
        $registration = $this->createRegistration();

        $response = $this->putJson(
            "/api/tax-registrations/{$registration->uuid}",
            [
                'registration_number' => '33LDTPS3357R1Z7',
                'state_code' => 'KA',
            ]
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tax registration updated successfully.',
            ]);

        $this->assertDatabaseHas('tax_registrations', [
            'id' => $registration->id,
            'registration_number' => '33LDTPS3357R1Z7',
            'state_code' => 'KA',
        ]);
    }

    public function test_registration_can_be_deleted(): void
    {
        $registration = $this->createRegistration();

        $response = $this->deleteJson(
            "/api/tax-registrations/{$registration->uuid}"
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tax registration deleted successfully.',
            ]);

        $this->assertSoftDeleted(
            'tax_registrations',
            [
                'id' => $registration->id,
            ]
        );
    }

    public function test_non_existing_registration_returns_not_found(): void
    {
        $response = $this->getJson(
            '/api/tax-registrations/00000000-0000-0000-0000-000000000000'
        );

        $response
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Tax registration not found.',
                'errors' => null,
            ]);
    }

    public function test_invalid_gstin_is_rejected(): void
    {
        $response = $this->postJson(
            '/api/tax-registrations',
            array_merge(
                $this->registrationPayload(),
                [
                    'registration_number' => 'INVALID',
                ]
            )
        );

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid GSTIN format.',
            ]);
    }

    public function test_duplicate_registration_number_is_rejected(): void
    {
        $this->createRegistration();

        $response = $this->postJson(
            '/api/tax-registrations',
            array_merge(
                $this->registrationPayload(),
                [
                    'registrable_id' => 2,
                    'is_primary' => false,
                ]
            )
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'success',
                false
            );
    }

    protected function registrationPayload(): array
    {
        return [
            'registrable_type' => 'customer',
            'registrable_id' => 1,
            'registration_type' => 'GSTIN',
            'registration_number' => '22AAAAA0000A1Z5',
            'country_code' => 'IN',
            'state_code' => 'WB',
            'is_primary' => true,
            'is_active' => true,
            'effective_from' => now()->toDateString(),
        ];
    }

    protected function createRegistration(): TaxRegistration
    {
        return TaxRegistration::query()->create([
            'tenant_id' => $this->tenant->id,
            'registrable_type' => 'customer',
            'registrable_id' => 1,
            'registration_type' => 'GSTIN',
            'registration_number' => '22AAAAA0000A1Z5',
            'country_code' => 'IN',
            'state_code' => 'WB',
            'is_primary' => false,
            'is_active' => true,
            'effective_from' => now()->toDateString(),
        ]);
    }
}