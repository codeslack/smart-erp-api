<?php

namespace App\Modules\PurchaseReturn\Services\Actions;

use App\Core\Exceptions\BusinessException;
use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Repositories\Contracts\PurchaseReturnRepositoryInterface;
use App\Modules\PurchaseReturn\Services\Posting\PurchaseReturnInventoryPostingService;
use Illuminate\Support\Facades\DB;

class ConfirmPurchaseReturnAction
{
    public function __construct(
        protected PurchaseReturnRepositoryInterface $repository,
        protected PurchaseReturnInventoryPostingService $inventoryPostingService,
        protected AccountingPostingServiceInterface $accountingPostingService,
    ) {}

    public function execute(PurchaseReturn $purchaseReturn): PurchaseReturn
    {
        return DB::transaction(function () use ($purchaseReturn) {
            /** @var PurchaseReturn $purchaseReturn */
            $purchaseReturn = PurchaseReturn::query()
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->id);

            if ($purchaseReturn->status !== PurchaseReturnStatus::DRAFT) {
                throw new BusinessException('Only draft purchase returns can be confirmed.');
            }

            $purchase = $purchaseReturn->purchase;
            if (! $purchase || $purchase->status !== PurchaseStatusEnum::CONFIRMED) {
                throw new BusinessException('Referenced purchase must be confirmed.');
            }

            // 1. Post inventory stock out
            $this->inventoryPostingService->post($purchaseReturn);

            // 2. Update status to CONFIRMED
            $purchaseReturn->update([
                'status' => PurchaseReturnStatus::CONFIRMED->value,
                'approved_by' => userId(),
                'approved_at' => now(),
            ]);

            // 3. Post accounting voucher
            $this->accountingPostingService->postPurchaseReturn($purchaseReturn);

            // 4. Adjust referenced Purchase due amount for credit notes
            if ($purchaseReturn->refund_type === PurchaseReturnRefundType::CREDIT_NOTE) {
                $newDue = max(0, round((float) $purchase->due_amount - (float) $purchaseReturn->grand_total, 4));
                $purchase->update([
                    'due_amount' => $newDue,
                ]);
            }

            return $this->repository->find($purchaseReturn->id);
        });
    }
}
