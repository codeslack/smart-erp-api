<?php

namespace Tests\Feature\User;

use App\Core\Tenant\TenantManager;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class UserTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected User $tenantAUser;

    protected User $tenantBUser;

    protected string $tenantAToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Tenant A and its user.
        $tenantA = $this->createTestTenant();

        $this->tenantAUser = $this->createTestUser();

        // Create Tenant B and its user.
        $tenantB = $this->createTestTenant();

        $this->tenantBUser = $this->createTestUser();

        // Return to Tenant A.
        app(TenantManager::class)->setTenant($tenantA);

        // Generate a real Sanctum bearer token.
        $this->tenantAToken = $this->tenantAUser
            ->createToken('tenant-a-test')
            ->plainTextToken;

        // Test route uses the actual tenant bootstrap sequence.
        Route::middleware([
            'api',
            'tenant.bootstrap',
            'auth:sanctum',
            'tenant',
        ])->get(
            '/api/test-tenant-users/{user}',
            function (User $user) {
                return response()->json([
                    'uuid' => $user->uuid,
                ]);
            }
        );
    }

    public function test_tenant_a_can_access_its_own_user(): void
    {
        $this->withToken($this->tenantAToken)
            ->getJson(
                '/api/test-tenant-users/' . $this->tenantAUser->uuid
            )
            ->assertOk()
            ->assertJson([
                'uuid' => $this->tenantAUser->uuid,
            ]);
    }

    public function test_tenant_a_cannot_access_tenant_b_user(): void
    {
        $this->withToken($this->tenantAToken)
            ->getJson(
                '/api/test-tenant-users/' . $this->tenantBUser->uuid
            )
            ->assertNotFound();
    }
}