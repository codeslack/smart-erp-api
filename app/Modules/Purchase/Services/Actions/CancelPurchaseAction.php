<?php

namespace App\Modules\Purchase\Services\Actions;

use App\Core\Exceptions\BusinessException;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use Illuminate\Support\Facades\DB;

class CancelPurchaseAction
{
    public function execute(Purchase $purchase, ?string $reason = null): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason) {
            $purchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if ($purchase->isCancelled()) {
                throw new BusinessException('Purchase is already cancelled.');
            }

            if (! $purchase->isDraft()) {
                throw new BusinessException(
                    'Only draft purchases can be cancelled. Use a purchase return for confirmed stock.'
                );
            }

            if ((float) $purchase->paid_amount > 0) {
                throw new BusinessException('Cannot cancel purchase with allocated payments or advances.');
            }

            if ($purchase->purchaseReturns()->exists()) {
                throw new BusinessException('Cannot cancel purchase with existing purchase returns.');
            }

            $purchase->update([
                'status' => PurchaseStatusEnum::CANCELLED->value,
                'cancelled_by' => userId(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            return $purchase->fresh();
        });
    }
}

