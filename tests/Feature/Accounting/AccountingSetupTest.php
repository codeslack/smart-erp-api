<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Modules\Tenant\Models\Tenant;

use App\Modules\Accounting\Models\AccountGroup;
use App\Modules\Accounting\Models\ChartOfAccount;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\AccountingAccounts;

use App\Modules\Accounting\Services\AccountingSetupService;

class AccountingSetupTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected AccountingSetupService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        $this->service = app(
            AccountingSetupService::class
        );
    }

    public function test_setup_creates_default_account_groups_and_accounts(): void
    {
        $this->service->setup(
            $this->tenant
        );

        $this->assertDatabaseCount(
            'account_groups',
            5
        );

        $this->assertDatabaseCount(
            'chart_of_accounts',
            16
        );

        $this->assertDatabaseHas(
            'account_groups',
            [
                'tenant_id' => $this->tenant->id,
                'name' => 'Assets',
                'code' => 'AST',
            ]
        );

        $this->assertDatabaseHas(
            'account_groups',
            [
                'tenant_id' => $this->tenant->id,
                'name' => 'Liabilities',
                'code' => 'LIA',
            ]
        );

        $this->assertDatabaseHas(
            'chart_of_accounts',
            [
                'tenant_id' => $this->tenant->id,
                'account_code' => AccountingAccounts::CASH,
                'account_name' => 'Cash',
                'account_type' => AccountType::ASSET,
                'is_system' => true,
                'is_active' => true,
            ]
        );

        $this->assertDatabaseHas(
            'chart_of_accounts',
            [
                'tenant_id' => $this->tenant->id,
                'account_code' => AccountingAccounts::ACCOUNTS_RECEIVABLE,
                'account_name' => 'Accounts Receivable',
            ]
        );

        $this->assertDatabaseHas(
            'chart_of_accounts',
            [
                'tenant_id' => $this->tenant->id,
                'account_code' => AccountingAccounts::ACCOUNTS_PAYABLE,
                'account_name' => 'Accounts Payable',
            ]
        );

        $this->assertDatabaseHas(
            'chart_of_accounts',
            [
                'tenant_id' => $this->tenant->id,
                'account_code' => AccountingAccounts::OPENING_BALANCE_EQUITY,
                'account_name' => 'Opening Balance Equity',
                'account_type' => AccountType::EQUITY,
            ]
        );
    }

    public function test_setup_is_idempotent(): void
    {
        $this->service->setup(
            $this->tenant
        );

        $groupCount = AccountGroup::query()
            ->where(
                'tenant_id',
                $this->tenant->id
            )
            ->count();

        $accountCount = ChartOfAccount::query()
            ->where(
                'tenant_id',
                $this->tenant->id
            )
            ->count();

        $this->service->setup(
            $this->tenant
        );

        $this->assertEquals(
            $groupCount,
            AccountGroup::query()
                ->where(
                    'tenant_id',
                    $this->tenant->id
                )
                ->count()
        );

        $this->assertEquals(
            $accountCount,
            ChartOfAccount::query()
                ->where(
                    'tenant_id',
                    $this->tenant->id
                )
                ->count()
        );
    }

    public function test_all_accounts_are_created_as_system_accounts(): void
    {
        $this->service->setup(
            $this->tenant
        );

        $this->assertEquals(
            16,
            ChartOfAccount::query()
                ->where(
                    'tenant_id',
                    $this->tenant->id
                )
                ->where(
                    'is_system',
                    true
                )
                ->count()
        );
    }

    public function test_all_accounts_start_with_zero_balances(): void
    {
        $this->service->setup(
            $this->tenant
        );

        $this->assertEquals(
            0,
            ChartOfAccount::query()
                ->where(
                    'tenant_id',
                    $this->tenant->id
                )
                ->where(
                    'opening_balance',
                    '!=',
                    0
                )
                ->count()
        );

        $this->assertEquals(
            0,
            ChartOfAccount::query()
                ->where(
                    'tenant_id',
                    $this->tenant->id
                )
                ->where(
                    'current_balance',
                    '!=',
                    0
                )
                ->count()
        );
    }
}