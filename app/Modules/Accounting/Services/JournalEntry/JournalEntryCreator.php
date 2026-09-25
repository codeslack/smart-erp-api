<?php

namespace App\Modules\Accounting\Services\JournalEntry;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\JournalEntryLine;
use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;

class JournalEntryCreator
{
    public function __construct(
        protected JournalEntryRepositoryInterface $repository,
        protected JournalEntryValidator $validator,
    ) {}

    public function create(
        array $data
    ): JournalEntry {
        $lines = $data['lines'] ?? [];

        $this->validator->validateLines(
            $lines
        );

        unset($data['lines']);

        $journalEntry = $this->repository->create([
            ...$data,

            'voucher_no' => nextDocumentNumber(
                'journal_entry',
                'JV'
            ),
        ]);

        foreach ($lines as $line) {
            $account = $this->resolveAccount(
                $line,
                $journalEntry->tenant_id
            );

            JournalEntryLine::create([
                'tenant_id' =>
                    $journalEntry->tenant_id,

                'journal_entry_id' =>
                    $journalEntry->id,

                'chart_of_account_id' =>
                    $account->id,

                'debit' => $this->decimal(
                    $line['debit'] ?? '0'
                ),

                'credit' => $this->decimal(
                    $line['credit'] ?? '0'
                ),

                'description' =>
                    $line['description'] ?? null,
            ]);
        }

        return $journalEntry
            ->fresh()
            ->load([
                'lines.account',
            ]);
    }

    protected function resolveAccount(
        array $line,
        int $tenantId
    ): ChartOfAccount {
        if (
            ! isset(
                $line['chart_of_account_uuid']
            )
        ) {
            throw new BusinessException(
                'Chart of account UUID is required.'
            );
        }

        $account = ChartOfAccount::query()
            ->where(
                'tenant_id',
                $tenantId
            )
            ->where(
                'uuid',
                $line['chart_of_account_uuid']
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (! $account) {
            throw new BusinessException(
                'Active chart of account not found.'
            );
        }

        return $account;
    }

    protected function decimal(
        mixed $value
    ): string {
        if (
            $value === null
            || $value === ''
        ) {
            return '0.0000';
        }

        $value = (string) $value;

        if (! is_numeric($value)) {
            throw new BusinessException(
                'Journal entry amount must be numeric.'
            );
        }

        return bcadd(
            $value,
            '0',
            4
        );
    }
}