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

class AccountLedgerSameAccountTest extends TestCase
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

    public function test_journal_with_multiple_lines_for_same_account_rebuilds_correctly(): void
    {
        $createResponse = $this->postJson(
            '/api/accounting/journal-entries',
            [
                'voucher_type' => 'JOURNAL',
                'entry_date' => '2026-09-20',
                'description' => 'Same account test',
                'lines' => [
                    [
                        'chart_of_account_uuid'
                            => $this->cashAccount->uuid,
                        'debit' => 1000,
                        'credit' => 0,
                    ],
                    [
                        'chart_of_account_uuid'
                            => $this->cashAccount->uuid,
                        'debit' => 500,
                        'credit' => 0,
                    ],
                    [
                        'chart_of_account_uuid'
                            => $this->salesAccount->uuid,
                        'debit' => 0,
                        'credit' => 1500,
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

        $ledgers = AccountLedger::query()
            ->where('tenant_id', $this->tenant->id)
            ->where(
                'chart_of_account_id',
                $this->cashAccount->id
            )
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $ledgers);

        $this->assertSame(
            '1000.0000',
            (string) $ledgers[0]->running_balance
        );

        $this->assertSame(
            '1500.0000',
            (string) $ledgers[1]->running_balance
        );

        $this->assertSame(
            '1500.0000',
            (string) $this->cashAccount
                ->fresh()
                ->current_balance
        );

        $this->assertDatabaseCount(
            'account_ledgers',
            3
        );
    }
}