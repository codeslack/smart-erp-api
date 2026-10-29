<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Tests\Support\CreatesTenant;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use App\Core\Tenant\TenantManager;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Accounting\Models\AccountGroup;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Services\AccountingSetupService;

class ChartOfAccountApiTest extends TestCase
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
            'name' => 'Accounting Admin',
            'email' => 'acct-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
        app(AccountingSetupService::class)->setup($this->tenant);
    }

    public function test_can_list_account_groups(): void
    {
        $response = $this->getJson('/api/accounting/account-groups');

        $response->assertSuccessful()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data'));
        $this->assertTrue(Str::isUuid($response->json('data.0.uuid')));
    }

    public function test_can_create_account_group(): void
    {
        $response = $this->postJson('/api/accounting/account-groups', [
            'name' => 'Current Assets Sub',
            'code' => 'CAS-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Current Assets Sub');

        $this->assertTrue(Str::isUuid($response->json('data.uuid')));
    }

    public function test_can_list_chart_of_accounts(): void
    {
        $response = $this->getJson('/api/accounting/chart-of-accounts');

        $response->assertSuccessful()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('data'));
        $this->assertTrue(Str::isUuid($response->json('data.0.uuid')));
    }

    public function test_can_create_chart_of_account_using_group_uuid(): void
    {
        $group = AccountGroup::query()->firstOrFail();

        $response = $this->postJson('/api/accounting/chart-of-accounts', [
            'account_group_uuid' => $group->uuid,
            'account_code' => '9999',
            'account_name' => 'Petty Cash Reserve',
            'account_type' => AccountType::ASSET,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account_code', '9999')
            ->assertJsonPath('data.account_name', 'Petty Cash Reserve');

        $accountUuid = $response->json('data.uuid');
        $this->assertTrue(Str::isUuid($accountUuid));

        $this->assertDatabaseHas('chart_of_accounts', [
            'uuid' => $accountUuid,
            'tenant_id' => $this->tenant->id,
            'account_code' => '9999',
            'account_group_id' => $group->id,
        ]);
    }

    public function test_can_show_chart_of_account_using_uuid(): void
    {
        $account = ChartOfAccount::query()->firstOrFail();

        $response = $this->getJson("/api/accounting/chart-of-accounts/{$account->uuid}");

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $account->uuid)
            ->assertJsonPath('data.account_code', $account->account_code);
    }

    public function test_can_update_chart_of_account(): void
    {
        $group = AccountGroup::query()->firstOrFail();

        $account = ChartOfAccount::query()->create([
            'tenant_id' => $this->tenant->id,
            'account_group_id' => $group->id,
            'account_code' => '8888',
            'account_name' => 'Custom Bank Account',
            'account_type' => AccountType::ASSET,
            'is_system' => false,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/accounting/chart-of-accounts/{$account->uuid}", [
            'account_name' => 'Custom Bank Account Updated',
            'account_code' => '8888',
            'account_type' => AccountType::ASSET,
            'is_active' => true,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account_name', 'Custom Bank Account Updated');
    }

    public function test_cannot_delete_system_account(): void
    {
        $systemAccount = ChartOfAccount::query()
            ->where('is_system', true)
            ->firstOrFail();

        $response = $this->deleteJson("/api/accounting/chart-of-accounts/{$systemAccount->uuid}");

        $response->assertStatus(422);
    }
}
