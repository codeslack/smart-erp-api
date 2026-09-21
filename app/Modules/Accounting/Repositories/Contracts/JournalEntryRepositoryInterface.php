<?php

namespace App\Modules\Accounting\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use App\Modules\Accounting\Models\JournalEntry;

interface JournalEntryRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findWithLines(
        int|string $id
    ): JournalEntry;

    public function findPostedByReference(
        string $referenceType,
        int $referenceId,
        string $voucherType
    ): ?JournalEntry;
}