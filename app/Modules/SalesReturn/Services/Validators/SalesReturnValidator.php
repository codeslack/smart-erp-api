<?php

namespace App\Modules\SalesReturn\Services\Validators;

use App\Modules\Sales\Models\SaleItem;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Models\SalesReturnItem;
use Illuminate\Validation\ValidationException;

class SalesReturnValidator
{
    public function validateForSave(array $header, array $items, ?int $ignoreReturnId = null): void
    {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['Sales return must have at least one line item.'],
            ]);
        }

        foreach ($items as $item) {
            $qty = (float) ($item['returned_quantity'] ?? $item['quantity'] ?? 0);
            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'items' => ['Returned quantity must be greater than zero.'],
                ]);
            }

            if (! empty($item['sale_item_id'])) {
                /** @var SaleItem|null $saleItem */
                $saleItem = SaleItem::query()->find($item['sale_item_id']);
                if ($saleItem) {
                    $soldQty = (float) $saleItem->quantity;

                    $alreadyReturned = (float) SalesReturnItem::query()
                        ->where('sale_item_id', $saleItem->id)
                        ->whereHas('salesReturn', function ($q) use ($ignoreReturnId) {
                            $q->whereIn('status', [SalesReturnStatus::DRAFT->value, SalesReturnStatus::CONFIRMED->value]);
                            if ($ignoreReturnId) {
                                $q->where('id', '!=', $ignoreReturnId);
                            }
                        })
                        ->sum('returned_quantity');

                    $maxReturnable = max(0, $soldQty - $alreadyReturned);

                    if ($qty > $maxReturnable) {
                        throw ValidationException::withMessages([
                            'items' => ["Returned quantity ({$qty}) exceeds available returnable quantity ({$maxReturnable}) for item: {$saleItem->item_name}."],
                        ]);
                    }
                }
            }
        }
    }

    public function validateForUpdate(SalesReturn $return): void
    {
        $status = $return->status instanceof SalesReturnStatus
            ? $return->status
            : SalesReturnStatus::tryFrom((string) $return->status);

        if ($status !== SalesReturnStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales returns can be updated.'],
            ]);
        }
    }

    public function validateForConfirmation(SalesReturn $return): void
    {
        $status = $return->status instanceof SalesReturnStatus
            ? $return->status
            : SalesReturnStatus::tryFrom((string) $return->status);

        if ($status !== SalesReturnStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales returns can be confirmed.'],
            ]);
        }

        if ($return->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Cannot confirm a sales return without items.'],
            ]);
        }
    }

    public function validateForCancellation(SalesReturn $return): void
    {
        $status = $return->status instanceof SalesReturnStatus
            ? $return->status
            : SalesReturnStatus::tryFrom((string) $return->status);

        if (! in_array($status, [SalesReturnStatus::DRAFT, SalesReturnStatus::CONFIRMED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel a sales return in ' . ($status?->label() ?? 'current') . ' status.'],
            ]);
        }
    }

    public function validateForDeletion(SalesReturn $return): void
    {
        $status = $return->status instanceof SalesReturnStatus
            ? $return->status
            : SalesReturnStatus::tryFrom((string) $return->status);

        if ($status !== SalesReturnStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales returns can be deleted.'],
            ]);
        }
    }
}
