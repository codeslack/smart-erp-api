<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\ApiController;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Resources\JournalEntryResource;
use App\Modules\Accounting\Requests\StoreJournalEntryRequest;
use App\Modules\Accounting\Requests\UpdateJournalEntryRequest;
use App\Modules\Accounting\Services\JournalEntry\JournalEntryService;

/**
 * @tag Accounting.Journal Entries
 */
class JournalEntryController extends ApiController
{
    public function __construct(
        protected JournalEntryService $service
    ) {}

    public function index()
    {
        $entries = $this->service->getAll();

        return $this->success(
            JournalEntryResource::collection($entries),
            'Journal Entries retrieved successfully'
        );
    }

    public function store(
        StoreJournalEntryRequest $request
    ) {
        $journalEntry = $this->service->create(
            $request->validated()
        );

        return $this->success(
            new JournalEntryResource($journalEntry),
            'Journal Entry created successfully',
            201
        );
    }

    public function show(
        JournalEntry $journalEntry
    ) {
        return $this->success(
            new JournalEntryResource(
                $journalEntry->load('lines.account')
            ),
            'Journal Entry retrieved successfully'
        );
    }

    public function update(
        UpdateJournalEntryRequest $request,
        JournalEntry $journalEntry
    ) {
        // Journal entry update will be implemented
        // after the V4 lifecycle rules are finalized.
    }

    public function destroy(
        JournalEntry $journalEntry
    ) {
        // Journal entry deletion will be implemented
        // after the V4 lifecycle rules are finalized.
    }

    public function post(
        JournalEntry $journalEntry
    ) {
        $journalEntry = $this->service->post(
            $journalEntry
        );

        return $this->success(
            new JournalEntryResource($journalEntry),
            'Journal Entry posted successfully'
        );
    }

    public function cancel(
        JournalEntry $journalEntry
    ) {
        $journalEntry = $this->service->cancel(
            $journalEntry
        );

        return $this->success(
            new JournalEntryResource($journalEntry),
            'Journal Entry cancelled successfully'
        );
    }
}