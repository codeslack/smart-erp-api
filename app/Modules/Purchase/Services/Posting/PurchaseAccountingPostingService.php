<?php

namespace App\Modules\Purchase\Services\Posting;

use App\Modules\Purchase\Models\Purchase;
use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;
use App\Modules\Accounting\Models\JournalEntry;

class PurchaseAccountingPostingService
{
    public function __construct(
        protected AccountingPostingServiceInterface $postingService
    ) {}

    public function post(Purchase $purchase): void
    {
        // Prevent duplicate posting
        if ($purchase->journal_entry_id !== null) {
            return;
        }

        $existing = JournalEntry::query()
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->first();

        if ($existing) {
            $purchase->update(['journal_entry_id' => $existing->id]);
            return;
        }

        $this->postingService->postPurchase($purchase);

        $created = JournalEntry::query()
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->latest('id')
            ->first();

        if ($created) {
            $purchase->update(['journal_entry_id' => $created->id]);
        }
    }
}
