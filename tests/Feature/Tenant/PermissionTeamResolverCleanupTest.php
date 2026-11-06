<?php

namespace Tests\Feature\Tenant;

use Tests\TestCase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Laravel\Sanctum\PersonalAccessToken;

use Spatie\Permission\PermissionRegistrar;

use App\Core\Tenant\TenantManager;

use App\Modules\User\Models\User;
use App\Modules\Tenant\Models\Tenant;

class PermissionTeamResolverCleanupTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;

    protected Tenant $tenantA;
    protected Tenant $tenantB;

    protected User $userA;
    protected User $userB;

    protected string $tenantAToken;
    protected string $tenantBToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = $this->createTestTenant();
        $this->userA = $this->createTestUser();

        $this->tenantAToken = $this->userA
            ->createToken('tenant-a-token')
            ->plainTextToken;

        $this->tenantB = $this->createTestTenant();
        $this->userB = $this->createTestUser();

        $this->tenantBToken = $this->userB
            ->createToken('tenant-b-token')
            ->plainTextToken;

        $this->assertSame(
            $this->tenantA->id,
            $this->userA->tenant_id
        );

        $this->assertSame(
            $this->tenantB->id,
            $this->userB->tenant_id
        );

        Route::middleware([
            'api',
            'tenant.bootstrap',
            'auth:sanctum',
            'tenant',
            'permission.tenant',
        ])->get('/api/test-permission-team', function (
            Request $request
        ) {
            $token = PersonalAccessToken::findToken(
                $request->bearerToken()
            );

            return response()->json([
                'tokenable_id' => $token?->tokenable_id,
                'tenant_id' => tenantId(),
                'team_id' => app(
                    PermissionRegistrar::class
                )->getPermissionsTeamId(),
            ]);
        });

        app(TenantManager::class)->clear();

        app(PermissionRegistrar::class)
            ->setPermissionsTeamId(null);

        Auth::forgetGuards();
    }

    public function test_permission_team_id_is_cleared_between_requests(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // First request: Tenant A.
        $responseA = $this->withToken($this->tenantAToken)
            ->getJson('/api/test-permission-team');

        $responseA
            ->assertOk()
            ->assertJson([
                'tokenable_id' => $this->userA->id,
                'tenant_id' => $this->tenantA->id,
                'team_id' => $this->tenantA->id,
            ]);

        $this->assertNull(
            $registrar->getPermissionsTeamId()
        );

        // Simulate a fresh HTTP request authentication lifecycle.
        Auth::forgetGuards();

        app(TenantManager::class)->clear();

        $registrar->setPermissionsTeamId(null);

        // Second request: Tenant B.
        $responseB = $this->withToken($this->tenantBToken)
            ->getJson('/api/test-permission-team');

        $responseB
            ->assertOk()
            ->assertJson([
                'tokenable_id' => $this->userB->id,
                'tenant_id' => $this->tenantB->id,
                'team_id' => $this->tenantB->id,
            ]);

        $this->assertNull(
            $registrar->getPermissionsTeamId()
        );

        $this->assertNull(
            app(TenantManager::class)->getTenant()
        );
    }
}
