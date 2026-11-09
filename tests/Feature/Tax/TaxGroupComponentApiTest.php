<?php

namespace Tests\Feature\Tax;

use Tests\TestCase;

use Laravel\Sanctum\Sanctum;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesUser;
use Tests\Support\CreatesTenant;

use App\Core\Tenant\TenantManager;
use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;

use App\Modules\Tax\Models\TaxGroup;
use App\Modules\Tax\Models\TaxComponent;
use App\Modules\Tax\Models\TaxGroupComponent;

use App\Modules\Tax\Services\TaxSetupService;

use App\Modules\Tax\Enums\TaxComponentTypeEnum;
use App\Modules\Tax\Enums\TaxCalculationMethodEnum;

class TaxGroupComponentApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenant;

    protected User $user;

    protected TaxGroup $taxGroup;

    protected TaxComponent $cgstComponent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TaxSetupService::class)->setup(
            $this->tenant
        );

        $this->taxGroup = TaxGroup::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('code', 'GST18-INTRA-IN')
            ->firstOrFail();

        $this->cgstComponent = TaxComponent::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'component_type',
                TaxComponentTypeEnum::CGST->value
            )
            ->firstOrFail();

        $this->user = $this->createTestUser();

        $this->grantCrudPermissions();

        Sanctum::actingAs($this->user);
    }

    protected function grantCrudPermissions(): void
    {
        $registrar = app(PermissionRegistrar::class);

        app(TenantManager::class)
            ->setTenant($this->tenant);

        $registrar->setPermissionsTeamId(
            $this->tenant->id
        );

        try {
            $role = Role::firstOrCreate([
                'tenant_id' => $this->tenant->id,
                'name' => 'Tax Group Component Test Role',
                'guard_name' => 'sanctum',
            ]);

            foreach ([
                'tax-group-component.view',
                'tax-group-component.create',
                'tax-group-component.update',
                'tax-group-component.delete',
            ] as $permissionName) {

                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'sanctum',
                ]);

                $role->givePermissionTo($permission);
            }

            $this->user->assignRole($role);

        } finally {
            $registrar->setPermissionsTeamId(null);

            $registrar->forgetCachedPermissions();
        }
    }

    public function test_group_component_can_be_created(): void
    {
        $component = TaxComponent::query()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'TEST-CESS',
            'name' => 'Test Cess',
            'component_type' =>
                TaxComponentTypeEnum::CESS->value,
            'is_active' => true,
        ]);

        $response = $this->postJson(
            '/api/tax-group-components',
            [
                'tax_group_id' => $this->taxGroup->id,
                'tax_component_id' => $component->id,
                'rate' => 10,
                'calculation_method' =>
                    TaxCalculationMethodEnum::PERCENTAGE->value,
                'is_active' => true,
                'effective_from' =>
                    now()->addDay()->toDateString(),
                'effective_to' => null,
                'sort_order' => 1,
            ]
        );

        $response
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' =>
                    'Tax group component created successfully.',
            ]);

        $this->assertDatabaseHas(
            'tax_group_components',
            [
                'tenant_id' => $this->tenant->id,
                'tax_group_id' => $this->taxGroup->id,
                'tax_component_id' => $component->id,
                'rate' => 10,
            ]
        );
    }

    public function test_group_component_can_be_listed(): void
    {
        $component = $this->createGroupComponent();

        $response = $this->getJson(
            '/api/tax-group-components'
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' =>
                    'Tax group components retrieved successfully.',
            ]);
    }

    public function test_group_component_can_be_retrieved(): void
    {
        $component = $this->createGroupComponent();

        $response = $this->getJson(
            "/api/tax-group-components/{$component->uuid}"
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' =>
                    'Tax group component retrieved successfully.',
            ]);
    }

    public function test_group_component_can_be_updated(): void
    {
        $component = $this->createGroupComponent();

        $response = $this->putJson(
            "/api/tax-group-components/{$component->uuid}",
            [
                'rate' => 12,
                'sort_order' => 2,
            ]
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' =>
                    'Tax group component updated successfully.',
            ]);

        $this->assertDatabaseHas(
            'tax_group_components',
            [
                'id' => $component->id,
                'rate' => 12,
                'sort_order' => 2,
            ]
        );
    }

    public function test_group_component_can_be_deleted(): void
    {
        $component = $this->createGroupComponent();

        $response = $this->deleteJson(
            "/api/tax-group-components/{$component->uuid}"
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' =>
                    'Tax group component deleted successfully.',
            ]);

        $this->assertDatabaseMissing(
            'tax_group_components',
            [
                'id' => $component->id,
            ]
        );
    }

    public function test_non_existing_group_component_returns_not_found(): void
    {
        $response = $this->getJson(
            '/api/tax-group-components/' .
            '00000000-0000-0000-0000-000000000000'
        );

        $response
            ->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' =>
                    'Tax group component not found.',
                'errors' => null,
            ]);
    }

    public function test_negative_rate_is_rejected(): void
    {
        $response = $this->postJson(
            '/api/tax-group-components',
            [
                'tax_group_id' => $this->taxGroup->id,
                'tax_component_id' => $this->cgstComponent->id,
                'rate' => -5,
                'calculation_method' =>
                    TaxCalculationMethodEnum::PERCENTAGE->value,
                'is_active' => true,
                'effective_from' => now()->addDay()->toDateString(),
                'effective_to' => null,
                'sort_order' => 1,
            ]
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath(
                'success',
                false
            );
    }

    protected function createGroupComponent(): TaxGroupComponent
    {
        $component = TaxComponent::query()->create([
            'tenant_id' => $this->tenant->id,
            'code' => uniqid('TEST-'),
            'name' => uniqid('Test Component '),
            'component_type' =>
                TaxComponentTypeEnum::CESS->value,
            'is_active' => true,
        ]);

        return TaxGroupComponent::query()->create([
            'tenant_id' => $this->tenant->id,
            'tax_group_id' => $this->taxGroup->id,
            'tax_component_id' => $component->id,
            'rate' => 10,
            'calculation_method' =>
                TaxCalculationMethodEnum::PERCENTAGE->value,
            'is_active' => true,
            'effective_from' => now()->addDay()->toDateString(),
            'effective_to' => null,
            'sort_order' => 1,
        ]);
    }
}
