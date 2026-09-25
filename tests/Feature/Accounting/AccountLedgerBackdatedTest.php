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

use App\Modules\Accounting\Models\AccountLedger;
use App\Modules\Accounting\Models\ChartOfAccount;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Services\AccountingSetupService;

class AccountLedgerBackdatedTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected Tenant $tenant;

    protected User $user;

    protected ChartOfAccount $cashAccount;

    protected ChartOfAccount $salesAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();

        app(TenantManager::class)
            ->setTenant($this->tenant);

        $this->user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => 'TEST-USER',
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'ledger-' . uniqid() . '@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);

        app(AccountingSetupService::class)
            ->setup($this->tenant);

        $this->cashAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::CASH
            )
            ->firstOrFail();

        $this->salesAccount = ChartOfAccount::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'account_code',
                AccountingAccounts::SALES_REVENUE
            )
            ->firstOrFail();
    }

    public function test_backdated_journal_rebuilds_running_balances(): void
    {
        $this->postAndAssertSuccess(
            '2026-09-20',
            1000,
            0
        );

        $this->postAndAssertSuccess(
            '2026-09-21',
            500,
            0
        );

        $this->postAndAssertSuccess(
            '2026-09-22',
            0,
            200
        );

        $this->assertSame(
            '1300.0000',
            (string) $this->cashAccount
                ->fresh()
                ->current_balance
        );

        $this->postAndAssertSuccess(
            '2026-09-18',
            300,
            0
        );

        $ledgers = AccountLedger::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'chart_of_account_id',
                $this->cashAccount->id
            )
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $this->assertCount(4, $ledgers);

        $this->assertSame(
            '2026-09-18',
            $ledgers[0]->entry_date->toDateString()
        );

        $this->assertSame(
            '300.0000',
            (string) $ledgers[0]->running_balance
        );

        $this->assertSame(
            '2026-09-20',
            $ledgers[1]->entry_date->toDateString()
        );

        $this->assertSame(
            '1300.0000',
            (string) $ledgers[1]->running_balance
        );

        $this->assertSame(
            '2026-09-21',
            $ledgers[2]->entry_date->toDateString()
        );

        $this->assertSame(
            '1800.0000',
            (string) $ledgers[2]->running_balance
        );

        $this->assertSame(
            '2026-09-22',
            $ledgers[3]->entry_date->toDateString()
        );

        $this->assertSame(
            '1600.0000',
            (string) $ledgers[3]->running_balance
        );

        $this->assertSame(
            '1600.0000',
            (string) $this->cashAccount
                ->fresh()
                ->current_balance
        );
    }

    protected function postAndAssertSuccess(
        string $entryDate,
        int $debit,
        int $credit
    ): void {
        $createResponse = $this->postJson(
            '/api/accounting/journal-entries',
            [
                'voucher_type' => 'JOURNAL',
                'entry_date' => $entryDate,
                'description' => "Test journal {$entryDate}",
                'lines' => [
                    [
                        'chart_of_account_uuid'
                            => $this->cashAccount->uuid,
                        'debit' => $debit,
                        'credit' => $credit,
                    ],
                    [
                        'chart_of_account_uuid'
                            => $this->salesAccount->uuid,
                        'debit' => $credit,
                        'credit' => $debit,
                    ],
                ],
            ]
        );

        $createResponse
            ->assertSuccessful();

        $uuid = $createResponse->json(
            'data.uuid'
        );

        $this->assertTrue(
            Str::isUuid($uuid)
        );

        $this->postJson(
            "/api/accounting/journal-entries/{$uuid}/post"
        )
            ->assertSuccessful()
            ->assertJsonPath(
                'data.status',
                'posted'
            );
    }
}