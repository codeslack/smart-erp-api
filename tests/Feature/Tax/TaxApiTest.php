<?php

namespace Tests\Feature\Tax;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use App\Core\Tenant\TenantManager;

use App\Modules\Rbac\Models\Permission;
use App\Modules\Rbac\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Tax\Models\Tax;
use App\Modules\Tax\Enums\TaxTypeEnum;

class TaxApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)->setTenant($this->tenant);

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TEST-USER',
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

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
                'name' => 'Tax API Test Role',
                'guard_name' => 'sanctum',
            ]);

            foreach ([
                'tax.view',
                'tax.create',
                'tax.update',
                'tax.delete',
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

    public function test_tax_can_be_created(): void
    {
        $payload = $this->validPayload();

        $response = $this->postJson(
            route('taxes.store'),
            $payload
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', $payload['name'])
            ->assertJsonPath('data.tax_type', $payload['tax_type']);

        $this->assertDatabaseHas('taxes', [
            'tenant_id' => $this->tenant->id,
            'name' => $payload['name'],
            'tax_type' => $payload['tax_type'],
            'description' => $payload['description'],
            'is_active' => true,
            'effective_from' => $payload['effective_from'],
        ]);

        app(\App\Core\Tenant\TenantManager::class)
            ->setTenant($this->tenant);

        $this->assertNotNull(
            Tax::query()
                ->where('tenant_id', $this->tenant->id)
                ->where('name', $payload['name'])
                ->first()
                ?->code
        );
    }

    public function test_taxes_can_be_listed(): void
    {
        Tax::query()->create([
            'code' => 'TAX-TEST-001',
            'name' => 'GST Tax',
            'tax_type' => TaxTypeEnum::GST->value,
            'description' => 'GST Tax',
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this->getJson(
            route('taxes.index')
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_tax_can_be_shown(): void
    {
        $tax = Tax::query()->create([
            'code' => 'TAX-TEST-001',
            'name' => 'GST Tax',
            'tax_type' => TaxTypeEnum::GST->value,
            'description' => 'GST Tax',
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this->getJson(
            route('taxes.show', $tax->uuid)
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $tax->uuid)
            ->assertJsonPath('data.name', $tax->name)
            ->assertJsonPath('data.tax_type', $tax->tax_type);
    }

    public function test_tax_can_be_updated(): void
    {
        $tax = Tax::query()->create([
            'code' => 'TAX-TEST-001',
            'name' => 'GST Tax',
            'tax_type' => TaxTypeEnum::GST->value,
            'description' => 'Old description',
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $payload = [
            'name' => 'Updated GST Tax',
            'description' => 'Updated description',
            'is_active' => false,
        ];

        $response = $this->putJson(
            route('taxes.update', $tax->uuid),
            $payload
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated GST Tax')
            ->assertJsonPath(
                'data.description',
                'Updated description'
            )
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('taxes', [
            'id' => $tax->id,
            'name' => 'Updated GST Tax',
            'description' => 'Updated description',
            'is_active' => false,
        ]);
    }

    public function test_tax_can_be_deleted(): void
    {
        $tax = Tax::query()->create([
            'code' => 'TAX-TEST-001',
            'name' => 'GST Tax',
            'tax_type' => TaxTypeEnum::GST->value,
            'description' => 'GST Tax',
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this->deleteJson(
            route('taxes.destroy', $tax->uuid)
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('taxes', [
            'id' => $tax->id,
        ]);
    }

    public function test_effective_to_cannot_be_before_effective_from(): void
    {
        $payload = $this->validPayload([
            'effective_from' => '2026-01-10',
            'effective_to' => '2026-01-01',
        ]);

        $response = $this->postJson(
            route('taxes.store'),
            $payload
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'effective_to',
            ]);
    }

    public function test_tax_type_must_be_valid(): void
    {
        $payload = $this->validPayload([
            'tax_type' => 'INVALID_TAX_TYPE',
        ]);

        $response = $this->postJson(
            route('taxes.store'),
            $payload
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'tax_type',
            ]);
    }

    public function test_tenant_can_only_see_own_tax(): void
    {
        $tax = Tax::query()->create([
            'code' => 'TAX-TEST-001',
            'name' => 'Tenant One GST',
            'tax_type' => TaxTypeEnum::GST->value,
            'description' => 'Tenant One GST',
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        $otherTenant = $this->createTestTenant();

        $otherTax = Tax::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'code' => 'TAX-OTHER-001',
            'name' => 'Tenant Two GST',
            'tax_type' => TaxTypeEnum::GST->value,
            'description' => 'Tenant Two GST',
            'is_active' => true,
            'effective_from' => '2026-01-01',
        ]);

        app(TenantManager::class)
            ->setTenant($this->tenant);

        $this->assertNotNull($tax);
        $this->assertNotNull($otherTax);

        $response = $this->getJson(
            route('taxes.index')
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath(
            'data.0.name',
            'Tenant One GST'
        );

        $this->getJson(
            route('taxes.show', $otherTax->uuid)
        )->assertNotFound();
    }

    protected function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'GST Tax',
            'tax_type' => TaxTypeEnum::GST->value,
            'description' => 'GST Tax',
            'is_active' => true,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ], $overrides);
    }
}
