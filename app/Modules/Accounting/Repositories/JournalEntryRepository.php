<?php

namespace App\Modules\Accounting\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\BaseRepository;

use App\Modules\Accounting\Enums\JournalEntryStatusEnum;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;

class JournalEntryRepository
    extends BaseRepository
    implements JournalEntryRepositoryInterface
{
    public function __construct(
        JournalEntry $model
    ) {
        parent::__construct($model);
    }

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->model
            ->newQuery()
            ->with(['lines.account'])
            ->latest()
            ->paginate($perPage);
    }

    public function findWithLines(
        int|string $id
    ): JournalEntry {
        return $this->model
            ->newQuery()
            ->with(['lines.account'])
            ->findOrFail($id);
    }

    public function findPostedByReference(
        string $referenceType,
        int $referenceId,
        string $voucherType
    ): ?JournalEntry {
        return $this->model
            ->newQuery()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('voucher_type', $voucherType)
            ->where(
                'status',
                JournalEntryStatusEnum::POSTED->value
            )
            ->whereNull('reversal_of_journal_entry_id')
            ->whereDoesntHave('reversal')
            ->with(['lines.account'])
            ->first();
    }
}