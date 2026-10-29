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
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Services\AccountingSetupService;

class AccountingReportsTest extends TestCase
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
            'name' => 'Report Admin',
            'email' => 'reports-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
        app(AccountingSetupService::class)->setup($this->tenant);
    }

    public function test_trial_balance_returns_balanced_report(): void
    {
        $response = $this->getJson('/api/accounting/trial-balance');

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_balanced', true);
    }

    public function test_balance_sheet_returns_report(): void
    {
        $response = $this->getJson('/api/accounting/balance-sheet?as_of_date=' . now()->toDateString());

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_balanced', true);
    }

    public function test_profit_loss_returns_report(): void
    {
        $response = $this->getJson(
            '/api/accounting/profit-loss?from_date=' . now()->startOfMonth()->toDateString() . '&to_date=' . now()->toDateString()
        );

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'income_accounts',
                    'expense_accounts',
                    'total_income',
                    'total_expense',
                    'net_profit',
                ],
            ]);
    }

    public function test_general_ledger_supports_account_uuid(): void
    {
        $cashAccount = ChartOfAccount::query()
            ->where('account_code', AccountingAccounts::CASH)
            ->firstOrFail();

        $response = $this->getJson("/api/accounting/general-ledger?account_uuid={$cashAccount->uuid}");

        $response->assertSuccessful()
            ->assertJsonPath('success', true);
    }

    public function test_day_book_returns_transactions_safely(): void
    {
        $response = $this->getJson('/api/accounting/day-book');

        $response->assertSuccessful()
            ->assertJsonPath('success', true);
    }

    public function test_cash_book_returns_report(): void
    {
        $response = $this->getJson('/api/accounting/cash-book');

        $response->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'opening_balance',
                    'transactions',
                    'closing_balance',
                ],
            ]);
    }
}
