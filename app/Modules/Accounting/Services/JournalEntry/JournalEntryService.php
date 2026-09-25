<?php

namespace App\Modules\Accounting\Services\JournalEntry;

use Illuminate\Support\Facades\DB;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Repositories\Contracts\JournalEntryRepositoryInterface;

class JournalEntryService
{
    public function __construct(
        protected JournalEntryRepositoryInterface $repository,
        protected JournalEntryCreator $creator,
        protected JournalEntryPoster $poster,
        protected JournalEntryCanceller $canceller,
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(
        int|string $id
    ): JournalEntry {
        return $this->repository->findById($id);
    }

    public function create(
        array $data
    ): JournalEntry {
        return DB::transaction(
            fn () => $this->creator->create($data)
        );
    }

    public function createAndPost(
        array $data
    ): JournalEntry {
        return DB::transaction(
            function () use ($data) {

                $journalEntry = $this->creator->create(
                    $data
                );

                return $this->poster->post(
                    $journalEntry
                );
            }
        );
    }

    public function post(
        JournalEntry $journalEntry
    ): JournalEntry {
        return $this->poster->post(
            $journalEntry
        );
    }

    public function cancel(
        JournalEntry $journalEntry
    ): JournalEntry {
        return $this->canceller->cancel(
            $journalEntry
        );
    }
}