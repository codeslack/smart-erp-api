<?php

namespace Tests\Feature\Tenant;

use App\Modules\Tenant\Models\Tenant;
use App\Core\Tenant\TenantManager;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TEST-USER',
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Route::middleware([
            'auth:sanctum',
            'tenant',
        ])->get(
            '/api/test-tenant-context',
            function () {
                return response()->json([
                    'tenant_id' => tenantId(),
                    'has_context' => isTenantContext(),
                ]);
            }
        );
    }

    public function test_authenticated_user_resolves_tenant(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/test-tenant-context');

        $response
            ->assertOk()
            ->assertJson([
                'tenant_id' => $this->tenant->id,
                'has_context' => true,
            ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/test-tenant-context');

        $response
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_inactive_tenant_is_rejected(): void
    {
        $this->tenant->update([
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/test-tenant-context');

        $response
            ->assertNotFound()
            ->assertJson([
                'success' => false,
                'code' => 'TENANT_NOT_FOUND',
            ]);
    }

    public function test_tenant_context_is_cleared_after_request(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/test-tenant-context')
            ->assertOk();

        $this->assertFalse(
            isTenantContext()
        );

        $this->assertNull(
            app(TenantManager::class)->getTenant()
        );
    }
}