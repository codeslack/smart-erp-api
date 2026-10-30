<?php

namespace App\Modules\Sales\Services\Posting;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\Postings\CustomerAdvanceAdjustmentPostingService;
use App\Modules\Accounting\Services\Postings\SalesPostingService;
use App\Modules\AdvanceAllocation\Services\CustomerAdvanceAutoAdjustmentService;
use App\Modules\Sales\Models\Sale;
use Throwable;

class SaleAccountingPostingService
{
    public function __construct(
        protected SalesPostingService $salesPostingService,
        protected CustomerAdvanceAutoAdjustmentService $customerAdvanceAutoAdjustmentService,
        protected CustomerAdvanceAdjustmentPostingService $customerAdvanceAdjustmentPostingService
    ) {}

    public function post(Sale $sale): void
    {
        try {
            $this->salesPostingService->post($sale);

            $adjustedAmount = $this->customerAdvanceAutoAdjustmentService->adjust($sale);
            if ($adjustedAmount > 0) {
                $this->customerAdvanceAdjustmentPostingService->post(
                    $sale->fresh(),
                    $adjustedAmount
                );
            }
        } catch (Throwable $e) {
            // Re-throw or allow graceful accounting failure if configured
            throw $e;
        }
    }

    public function reverse(Sale $sale): void
    {
        JournalEntry::query()
            ->where('tenant_id', tenantId())
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->delete();
    }
}
