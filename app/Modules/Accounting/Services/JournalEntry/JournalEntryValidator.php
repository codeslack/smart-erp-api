<?php

namespace App\Modules\Accounting\Services\JournalEntry;

use App\Core\Exceptions\BusinessException;

class JournalEntryValidator
{
    private const SCALE = 4;

    public function validateLines(array $lines): void
    {
        if (count($lines) < 2) {
            throw new BusinessException(
                'Journal entry must contain at least two lines.'
            );
        }

        $totalDebit = '0.0000';
        $totalCredit = '0.0000';

        foreach ($lines as $index => $line) {
            $debit = $this->decimal(
                $line['debit'] ?? '0'
            );

            $credit = $this->decimal(
                $line['credit'] ?? '0'
            );

            if (bccomp($debit, '0', self::SCALE) < 0) {
                throw new BusinessException(
                    "Journal entry line {$index} debit cannot be negative."
                );
            }

            if (bccomp($credit, '0', self::SCALE) < 0) {
                throw new BusinessException(
                    "Journal entry line {$index} credit cannot be negative."
                );
            }

            $hasDebit = bccomp(
                $debit,
                '0',
                self::SCALE
            ) > 0;

            $hasCredit = bccomp(
                $credit,
                '0',
                self::SCALE
            ) > 0;

            if (! $hasDebit && ! $hasCredit) {
                throw new BusinessException(
                    "Journal entry line {$index} must contain a debit or credit amount."
                );
            }

            if ($hasDebit && $hasCredit) {
                throw new BusinessException(
                    "Journal entry line {$index} cannot contain both debit and credit."
                );
            }

            $totalDebit = bcadd(
                $totalDebit,
                $debit,
                self::SCALE
            );

            $totalCredit = bcadd(
                $totalCredit,
                $credit,
                self::SCALE
            );
        }

        if (bccomp(
            $totalDebit,
            $totalCredit,
            self::SCALE
        ) !== 0) {
            throw new BusinessException(
                'Journal entry is not balanced.'
            );
        }
    }

    private function decimal(mixed $value): string
    {
        if ($value === null || $value === '') {
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
            self::SCALE
        );
    }
}