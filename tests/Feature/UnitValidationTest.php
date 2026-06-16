<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Unit\Models\Unit;
use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UnitValidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantOne;
    private Tenant $tenantTwo;
    private User $userOne;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create two distinct tenants
        $this->tenantOne = Tenant::factory()->create();
        $this->tenantTwo = Tenant::factory()->create();

        // 2. Create and authenticate a user belonging to Tenant One
        $this->userOne = User::factory()->create([
            'tenant_id' => $this->tenantOne->id
        ]);

        // 4. Authenticate the user
        $this->actingAs($this->userOne);

        session(['tenant_id' => $this->tenantOne->slug]);

        $this->withHeaders(['X-Tenant' => $this->tenantOne->slug]);
    }

    public function test_a_user_can_create_a_unit_with_a_unique_name_within_their_tenant(): void
    {
        $response = $this->postJson(route('units.store'), [
            'name'       => 'Kilogram',
            'short_name' => 'kg',
            'is_active'  => true,
        ]);

        app('log')->info([$response]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Kilogram']);

        $this->assertDatabaseHas('units', [
            'tenant_id' => $this->tenantOne->id,
            'name'      => 'Kilogram',
        ]);
    }

    public function test_a_user_cannot_create_a_unit_with_a_duplicate_name_within_the_same_tenant(): void
    {
        // Pre-create an existing unit for Tenant One
        Unit::factory()->create([
            'tenant_id'  => $this->tenantOne->id,
            'name'       => 'Kilogram',
            'short_name' => 'kg',
        ]);

        // Attempting to create a duplicate name should trigger a validation error
        $response = $this->postJson(route('units.store'), [
            'name'       => 'Kilogram',
            'short_name' => 'kg-alt',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_different_tenants_can_use_the_exact_same_unit_name_without_conflicts(): void
    {
        // Pre-create a unit under Tenant Two
        Unit::factory()->create([
            'tenant_id'  => $this->tenantTwo->id,
            'name'       => 'Kilogram',
            'short_name' => 'kg',
        ]);

        // Tenant One user attempts to create the same name (Should pass!)
        $response = $this->postJson(route('units.store'), [
            'name'       => 'Kilogram',
            'short_name' => 'kg',
        ]);

        $response->assertStatus(201);
        
        // Validate both unique rows exist independently in the database
        $this->assertDatabaseCount('units', 2);
    }

    public function test_a_user_can_update_their_own_unit_without_triggering_a_unique_validation_error_on_itself(): void
    {
        $unit = Unit::factory()->create([
            'tenant_id'  => $this->tenantOne->id,
            'name'       => 'Kilogram',
            'short_name' => 'kg',
        ]);

        // Send a put request modifying just the status while retaining the name
        $response = $this->putJson(route('units.update', ['unit' => $unit->id]), [
            'name'       => 'Kilogram',
            'short_name' => 'kg',
            'is_active'  => false,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('units', [
            'id'        => $unit->id,
            'is_active' => false,
        ]);
    }
}
