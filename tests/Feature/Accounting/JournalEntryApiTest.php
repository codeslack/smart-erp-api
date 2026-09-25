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

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\ChartOfAccount;

use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Services\AccountingSetupService;

class JournalEntryApiTest extends TestCase
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
            'email' => 'journal-' . uniqid() . '@example.com',
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

    public function test_journal_entry_can_be_created(): void
    {
        $response = $this->postJson(
            '/api/accounting/journal-entries',
            $this->validJournalData()
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.voucher_type', 'JOURNAL')
            ->assertJsonPath(
                'data.description',
                'Test journal entry'
            );

        $journalEntryUuid = $response->json('data.id');

        $this->assertTrue(
            Str::isUuid($journalEntryUuid)
        );

        $this->assertDatabaseHas(
            'journal_entries',
            [
                'uuid' => $journalEntryUuid,
                'tenant_id' => $this->tenant->id,
                'voucher_type' => 'JOURNAL',
                'status' => 'draft',
                'description' => 'Test journal entry',
            ]
        );

        $journalEntry = JournalEntry::query()
            ->where('uuid', $journalEntryUuid)
            ->firstOrFail();

        $this->assertDatabaseCount(
            'journal_entry_lines',
            2
        );

        $this->assertDatabaseHas(
            'journal_entry_lines',
            [
                'tenant_id' => $this->tenant->id,
                'journal_entry_id' => $journalEntry->id,
                'chart_of_account_id' => $this->cashAccount->id,
                'debit' => 1000,
                'credit' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'journal_entry_lines',
            [
                'tenant_id' => $this->tenant->id,
                'journal_entry_id' => $journalEntry->id,
                'chart_of_account_id' => $this->salesAccount->id,
                'debit' => 0,
                'credit' => 1000,
            ]
        );
    }

    public function test_journal_entry_generates_voucher_number(): void
    {
        $response = $this->postJson(
            '/api/accounting/journal-entries',
            $this->validJournalData()
        );

        $response->assertSuccessful();

        $voucherNo = $response->json(
            'data.voucher_no'
        );

        $this->assertNotEmpty($voucherNo);

        $this->assertDatabaseHas(
            'journal_entries',
            [
                'tenant_id' => $this->tenant->id,
                'voucher_no' => $voucherNo,
            ]
        );
    }

    public function test_journal_entries_can_be_listed(): void
    {
        $this->postJson(
            '/api/accounting/journal-entries',
            $this->validJournalData()
        )->assertSuccessful();

        $response = $this->getJson(
            '/api/accounting/journal-entries'
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath('success', true);

        $this->assertCount(
            1,
            $response->json('data')
        );

        $this->assertTrue(
            Str::isUuid(
                $response->json('data.0.id')
            )
        );
    }

    public function test_journal_entry_can_be_shown(): void
    {
        $createResponse = $this->postJson(
            '/api/accounting/journal-entries',
            $this->validJournalData()
        );

        $createResponse->assertSuccessful();

        $journalEntryUuid = $createResponse->json(
            'data.id'
        );

        $response = $this->getJson(
            "/api/accounting/journal-entries/{$journalEntryUuid}"
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.id',
                $journalEntryUuid
            );

        $this->assertCount(
            2,
            $response->json('data.lines')
        );
    }

    public function test_journal_entry_requires_at_least_two_lines(): void
    {
        $data = $this->validJournalData();

        $data['lines'] = [
            [
                'chart_of_account_id' => $this->cashAccount->id,
                'debit' => 1000,
                'credit' => 0,
            ],
        ];

        $response = $this->postJson(
            '/api/accounting/journal-entries',
            $data
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'lines',
            ]);

        $this->assertDatabaseCount(
            'journal_entries',
            0
        );
    }

    public function test_journal_entry_requires_valid_tenant_account(): void
    {
        $data = $this->validJournalData();

        $data['lines'][0]['chart_of_account_id'] = 999999999;

        $response = $this->postJson(
            '/api/accounting/journal-entries',
            $data
        );

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'lines.0.chart_of_account_id',
            ]);

        $this->assertDatabaseCount(
            'journal_entries',
            0
        );
    }

    public function test_journal_entry_can_be_posted(): void
    {
        $createResponse = $this->postJson(
            '/api/accounting/journal-entries',
            $this->validJournalData()
        );

        $createResponse->assertSuccessful();

        $journalEntryUuid = $createResponse->json(
            'data.id'
        );

        $postResponse = $this->postJson(
            "/api/accounting/journal-entries/{$journalEntryUuid}/post"
        );

        $postResponse
            ->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.id',
                $journalEntryUuid
            )
            ->assertJsonPath(
                'data.status',
                'posted'
            );

        $this->assertDatabaseHas(
            'journal_entries',
            [
                'uuid' => $journalEntryUuid,
                'tenant_id' => $this->tenant->id,
                'status' => 'posted',
            ]
        );

        $journalEntry = JournalEntry::query()
            ->where('uuid', $journalEntryUuid)
            ->firstOrFail();

        $this->assertDatabaseCount(
            'account_ledgers',
            2
        );

        $this->assertDatabaseHas(
            'account_ledgers',
            [
                'tenant_id' => $this->tenant->id,
                'journal_entry_id' => $journalEntry->id,
                'chart_of_account_id' => $this->cashAccount->id,
                'debit' => 1000,
                'credit' => 0,
            ]
        );

        $this->assertDatabaseHas(
            'account_ledgers',
            [
                'tenant_id' => $this->tenant->id,
                'journal_entry_id' => $journalEntry->id,
                'chart_of_account_id' => $this->salesAccount->id,
                'debit' => 0,
                'credit' => 1000,
            ]
        );
    }

    public function test_posting_journal_entry_updates_account_balances(): void
    {
        $this->assertSame(
            '0.0000',
            (string) $this->cashAccount
                ->fresh()
                ->current_balance
        );

        $this->assertSame(
            '0.0000',
            (string) $this->salesAccount
                ->fresh()
                ->current_balance
        );

        $createResponse = $this->postJson(
            '/api/accounting/journal-entries',
            $this->validJournalData()
        );

        $createResponse->assertSuccessful();

        $journalEntryUuid = $createResponse->json(
            'data.id'
        );

        $this->postJson(
            "/api/accounting/journal-entries/{$journalEntryUuid}/post"
        )->assertSuccessful();

        $this->cashAccount->refresh();
        $this->salesAccount->refresh();

        $this->assertSame(
            '1000.0000',
            (string) $this->cashAccount->current_balance
        );

        $this->assertSame(
            '1000.0000',
            (string) $this->salesAccount->current_balance
        );
    }

    public function test_draft_journal_entry_can_be_cancelled(): void
    {
        $createResponse = $this->postJson(
            '/api/accounting/journal-entries',
            $this->validJournalData()
        );

        $createResponse->assertSuccessful();

        $journalEntryUuid = $createResponse->json(
            'data.id'
        );

        $response = $this->postJson(
            "/api/accounting/journal-entries/{$journalEntryUuid}/cancel"
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath(
                'data.id',
                $journalEntryUuid
            )
            ->assertJsonPath(
                'data.status',
                'cancelled'
            );

        $this->assertDatabaseHas(
            'journal_entries',
            [
                'uuid' => $journalEntryUuid,
                'tenant_id' => $this->tenant->id,
                'status' => 'cancelled',
            ]
        );
    }

    public function test_posted_journal_entry_cannot_be_cancelled(): void
    {
        $createResponse = $this->postJson(
            '/api/accounting/journal-entries',
            $this->validJournalData()
        );

        $createResponse->assertSuccessful();

        $journalEntryUuid = $createResponse->json(
            'data.id'
        );

        $this->postJson(
            "/api/accounting/journal-entries/{$journalEntryUuid}/post"
        )->assertSuccessful();

        $response = $this->postJson(
            "/api/accounting/journal-entries/{$journalEntryUuid}/cancel"
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas(
            'journal_entries',
            [
                'uuid' => $journalEntryUuid,
                'tenant_id' => $this->tenant->id,
                'status' => 'posted',
            ]
        );
    }

    public function test_posted_journal_entry_cannot_be_posted_again(): void
    {
        $createResponse = $this->postJson(
            '/api/accounting/journal-entries',
            $this->validJournalData()
        );

        $createResponse->assertSuccessful();

        $journalEntryUuid = $createResponse->json(
            'data.id'
        );

        $this->postJson(
            "/api/accounting/journal-entries/{$journalEntryUuid}/post"
        )->assertSuccessful();

        $response = $this->postJson(
            "/api/accounting/journal-entries/{$journalEntryUuid}/post"
        );

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount(
            'account_ledgers',
            2
        );
    }

    protected function validJournalData(): array
    {
        return [
            'voucher_type' => 'JOURNAL',
            'entry_date' => now()->toDateString(),
            'description' => 'Test journal entry',
            'lines' => [
                [
                    'chart_of_account_id' => $this->cashAccount->id,
                    'debit' => 1000,
                    'credit' => 0,
                    'description' => 'Cash debit',
                ],
                [
                    'chart_of_account_id' => $this->salesAccount->id,
                    'debit' => 0,
                    'credit' => 1000,
                    'description' => 'Sales credit',
                ],
            ],
        ];
    }
}