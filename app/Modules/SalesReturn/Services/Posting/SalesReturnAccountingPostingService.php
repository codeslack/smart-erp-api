<?php

namespace App\Modules\SalesReturn\Services\Posting;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\Postings\SalesReturnPostingService;
use App\Modules\SalesReturn\Models\SalesReturn;
use Throwable;

class SalesReturnAccountingPostingService
{
    public function __construct(
        protected SalesReturnPostingService $salesReturnPostingService
    ) {}

    public function post(SalesReturn $salesReturn): void
    {
        try {
            $this->salesReturnPostingService->post($salesReturn);
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function reverse(SalesReturn $salesReturn): void
    {
        JournalEntry::query()
            ->where('tenant_id', tenantId())
            ->where('reference_type', SalesReturn::class)
            ->where('reference_id', $salesReturn->id)
            ->delete();
    }
}
