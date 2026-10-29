<?php

namespace App\Modules\Purchase\Services\Actions;

use App\Core\Exceptions\BusinessException;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface;
use App\Modules\Purchase\Services\Validators\PurchaseValidator;
use App\Modules\Purchase\Services\Posting\PurchaseInventoryPostingService;
use App\Modules\Purchase\Services\Posting\PurchaseAccountingPostingService;
use App\Modules\AdvanceAllocation\Services\SupplierAdvanceAutoAdjustmentService;
use App\Modules\Accounting\Services\Postings\SupplierAdvanceAdjustmentPostingService;
use Illuminate\Support\Facades\DB;

class ConfirmPurchaseAction
{
    public function __construct(
        protected PurchaseRepositoryInterface $repository,
        protected PurchaseValidator $validator,
        protected PurchaseInventoryPostingService $inventoryPostingService,
        protected PurchaseAccountingPostingService $accountingPostingService,
        protected SupplierAdvanceAutoAdjustmentService $advanceAdjustmentService,
        protected SupplierAdvanceAdjustmentPostingService $advancePostingService,
    ) {}

    public function execute(Purchase $purchase): Purchase
    {
        if (! $purchase->isDraft()) {
            throw new BusinessException('Only draft purchases can be confirmed.');
        }

        if ($purchase->items()->count() === 0) {
            throw new BusinessException('Cannot confirm a purchase invoice without line items.');
        }

        return DB::transaction(function () use ($purchase) {
            // Lock and re-validate cumulative quantities against GRN
            $this->validator->validateCumulativeBilledQuantities($purchase);

            // Post inventory movements (skips GRN-linked purchases, posts direct purchases)
            $this->inventoryPostingService->post($purchase);

            // Set confirmed status
            $purchase->update([
                'status' => PurchaseStatusEnum::CONFIRMED->value,
                'confirmed_by' => userId(),
                'confirmed_at' => now(),
            ]);

            // Post balanced double-entry accounting journal entry
            $this->accountingPostingService->post($purchase);

            // Auto-adjust against any pre-existing supplier advances
            $adjustedAmount = $this->advanceAdjustmentService->adjust($purchase);

            if ($adjustedAmount > 0) {
                $this->advancePostingService->post(
                    $purchase->fresh(),
                    $adjustedAmount
                );

                $paidAmount = round((float) $purchase->paid_amount + $adjustedAmount, 4);
                $dueAmount = max(0, round((float) $purchase->grand_total - $paidAmount, 4));
                $newStatus = ($dueAmount <= 0)
                    ? PurchaseStatusEnum::PAID->value
                    : PurchaseStatusEnum::PARTIALLY_PAID->value;

                $purchase->update([
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'status' => $newStatus,
                ]);
            }

            return $purchase->fresh()->load([
                'supplier',
                'warehouse',
                'purchaseOrder',
                'goodsReceiptNote',
                'paymentTerm',
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.batches',
                'items.serials',
                'charges.chartOfAccount',
                'charges.tax',
                'advanceAllocations.source',
                'paymentAllocations',
            ]);
        });
    }
}
