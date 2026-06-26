<?php

namespace App\Modules\Accounting\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;

class JournalEntryRepository
    extends BaseRepository
    implements JournalEntryRepositoryInterface
{
    public function __construct(
        JournalEntry $model
    ) {
        parent::__construct(
            $model
        );
    }
}
