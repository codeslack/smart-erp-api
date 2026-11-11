<?php

namespace App\Modules\PurchaseReturn\Services\Actions;

use App\Core\Exceptions\BusinessException;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Repositories\Contracts\PurchaseReturnRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CancelPurchaseReturnAction
{
    public function __construct(
        protected PurchaseReturnRepositoryInterface $repository,
    ) {}

    public function execute(PurchaseReturn $purchaseReturn, ?string $reason = null): PurchaseReturn
    {
        return DB::transaction(function () use ($purchaseReturn, $reason) {
            $purchaseReturn = PurchaseReturn::query()
                ->lockForUpdate()
                ->findOrFail($purchaseReturn->id);

            if ($purchaseReturn->status === PurchaseReturnStatus::CANCELLED) {
                throw new BusinessException('Purchase return is already cancelled.');
            }

            if ($purchaseReturn->status === PurchaseReturnStatus::CONFIRMED) {
                throw new BusinessException('Cannot cancel a confirmed purchase return.');
            }

            $purchaseReturn->update([
                'status' => PurchaseReturnStatus::CANCELLED->value,
                'notes' => $reason
                    ? trim($purchaseReturn->notes . "\nCancellation reason: " . $reason)
                    : $purchaseReturn->notes,
            ]);

            return $this->repository->find($purchaseReturn->id);
        });
    }
}
