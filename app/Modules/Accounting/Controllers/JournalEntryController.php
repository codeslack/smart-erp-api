<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\JournalEntryService;
use App\Modules\Accounting\Resources\JournalEntryResource;
use App\Modules\Accounting\Requests\StoreJournalEntryRequest;
use App\Modules\Accounting\Requests\UpdateJournalEntryRequest;

/**
 * @tag Accounting.Journal Entries
 */
class JournalEntryController
    extends Controller
{
    public function __construct(
        protected JournalEntryService $service
    ) {}

    public function index()
    {
        return JournalEntryResource::collection(
            $this->service->getAll()
        );
    }

    public function store(
        StoreJournalEntryRequest $request
    )
    {
        $journalEntry = $this->service->create(
            $request->validated()
        );

        return response()->json([

            'success' => true,

            'message'
                => 'Journal Entry created successfully',

            'data'
                => new JournalEntryResource(
                    $journalEntry
                ),
        ]);
    }

    public function show(
        JournalEntry $journalEntry
    )
    {
        return new JournalEntryResource(
            $journalEntry->load(
                'lines.account'
            )
        );
    }

    public function update(
        UpdateJournalEntryRequest $request,
        JournalEntry $journalEntry
    )
    {
        //
    }

    public function destroy(
        JournalEntry $journalEntry
    )
    {
        //
    }

    public function post(
        JournalEntry $journalEntry
    )
    {
        $journalEntry = $this->service->post(
            $journalEntry
        );

        return response()->json([

            'success' => true,

            'message'
                => 'Journal Entry posted successfully',

            'data'
                => new JournalEntryResource(
                    $journalEntry
                ),
        ]);
    }

    public function cancel(
        JournalEntry $journalEntry
    )
    {
        $journalEntry = $this->service->cancel(
            $journalEntry
        );

        return response()->json([

            'success' => true,

            'message'
                => 'Journal Entry cancelled successfully',

            'data'
                => new JournalEntryResource(
                    $journalEntry
                ),
        ]);
    }
}
