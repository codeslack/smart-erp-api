<?php

namespace App\Modules\SalesReturn\Services\Validators;

use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Models\SalesReturnItem;
use Illuminate\Validation\ValidationException;

class SalesReturnValidator
{
    public function validateForSave(array $header, array $items, ?int $ignoreReturnId = null): void
    {
        $sale = ! empty($header['sale_id'])
            ? Sale::query()->find($header['sale_id'])
            : null;

        if (
            ! $sale
            || ! in_array($sale->status, [
                SaleStatusEnum::CONFIRMED,
                SaleStatusEnum::PARTIALLY_PAID,
                SaleStatusEnum::PAID,
            ], true)
        ) {
            throw ValidationException::withMessages([
                'sale_uuid' => ['Sales returns can only be created against a confirmed sale.'],
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['Sales return must have at least one line item.'],
            ]);
        }

        foreach ($items as $index => $item) {
            $qty = (float) ($item['returned_quantity'] ?? $item['quantity'] ?? 0);
            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.returned_quantity" => ['Returned quantity must be greater than zero.'],
                ]);
            }

            if (empty($item['sale_item_id'])) {
                throw ValidationException::withMessages([
                    "items.{$index}.sale_item_uuid" => ['Each sales return line must reference an original sale item.'],
                ]);
            }

            /** @var SaleItem|null $saleItem */
            $saleItem = SaleItem::query()->find($item['sale_item_id']);
            if (! $saleItem || (int) $saleItem->sale_id !== (int) $sale->id) {
                throw ValidationException::withMessages([
                    "items.{$index}.sale_item_uuid" => ['The selected item does not belong to the referenced sale.'],
                ]);
            }

            if ((int) ($item['product_id'] ?? 0) !== (int) $saleItem->product_id) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_uuid" => ['The product does not match the original sale item.'],
                ]);
            }

            $soldQty = (float) $saleItem->quantity;
            $alreadyReturned = (float) SalesReturnItem::query()
                ->where('sale_item_id', $saleItem->id)
                ->whereHas('salesReturn', function ($q) use ($ignoreReturnId) {
                    $q->whereIn('status', [
                        SalesReturnStatus::DRAFT->value,
                        SalesReturnStatus::CONFIRMED->value,
                    ]);
                    if ($ignoreReturnId !== null) {
                        $q->where('id', '!=', $ignoreReturnId);
                    }
                })
                ->sum('returned_quantity');

            $maxReturnable = max(0, $soldQty - $alreadyReturned);
            if ($qty > $maxReturnable + 0.0001) {
                throw ValidationException::withMessages([
                    "items.{$index}.returned_quantity" => [
                        "Returned quantity ({$qty}) exceeds available returnable quantity ({$maxReturnable}).",
                    ],
                ]);
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
