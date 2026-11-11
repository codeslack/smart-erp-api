<?php

namespace App\Modules\SalesReturn\Services\Validators;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\AccountingAccounts;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleItemBatch;
use App\Modules\Sales\Models\SaleItemSerial;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;
use App\Modules\SalesReturn\Models\SaleReturnItemBatch;
use App\Modules\SalesReturn\Models\SaleReturnItemSerial;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Models\SalesReturnItem;
use Illuminate\Validation\ValidationException;

class SalesReturnValidator
{
    public function validateForSave(array $header, array $items, ?int $ignoreReturnId = null): void
    {
        $this->validateRefundDetails($header);

        $sale = ! empty($header['sale_id'])
            ? Sale::query()->lockForUpdate()->find($header['sale_id'])
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

        if (
            isset($header['customer_id'])
            && (int) $header['customer_id'] !== (int) $sale->customer_id
        ) {
            throw ValidationException::withMessages([
                'customer_uuid' => ['Customer does not match the referenced sale.'],
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['Sales return must have at least one line item.'],
            ]);
        }

        $quantitiesBySaleItem = [];
        $batchQuantitiesBySaleItem = [];
        $requestedSerialNumbers = [];

        foreach ($items as $item) {
            $saleItemId = (int) ($item['sale_item_id'] ?? 0);
            if ($saleItemId <= 0) {
                continue;
            }

            $quantitiesBySaleItem[$saleItemId] = ($quantitiesBySaleItem[$saleItemId] ?? 0.0)
                + (float) ($item['returned_quantity'] ?? $item['quantity'] ?? 0);

            foreach (($item['batches'] ?? []) as $batch) {
                $batchId = (int) ($batch['product_batch_id'] ?? 0);
                if ($batchId > 0) {
                    $batchQuantitiesBySaleItem[$saleItemId][$batchId] =
                        ($batchQuantitiesBySaleItem[$saleItemId][$batchId] ?? 0.0)
                        + (float) ($batch['quantity'] ?? 0);
                }
            }
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
            $saleItem = SaleItem::query()->with('product')->find($item['sale_item_id']);
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

            $quantityToValidate = $quantitiesBySaleItem[$saleItem->id] ?? $qty;
            $maxReturnable = max(0, $soldQty - $alreadyReturned);
            if ($quantityToValidate > $maxReturnable + 0.0001) {
                throw ValidationException::withMessages([
                    "items.{$index}.returned_quantity" => [
                        "Returned quantity ({$quantityToValidate}) exceeds available returnable quantity ({$maxReturnable}).",
                    ],
                ]);
            }

            if ($saleItem->product?->isBatchTracked()) {
                $batches = $item['batches'] ?? [];
                if ($batches === []) {
                    throw ValidationException::withMessages([
                        "items.{$index}.batches" => ['Batch details are required for this batch-tracked product.'],
                    ]);
                }

                $batchQuantity = array_sum(array_map(
                    fn (array $batch) => (float) ($batch['quantity'] ?? 0),
                    $batches
                ));

                if (abs($batchQuantity - $qty) > 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$index}.batches" => ['Return batch quantities must equal the returned item quantity.'],
                    ]);
                }

                foreach ($batches as $batchIndex => $batch) {
                    $batchId = (int) ($batch['product_batch_id'] ?? 0);
                    $batchNo = (string) ($batch['batch_no'] ?? '');

                    $sourceAllocation = $batchId > 0
                        ? SaleItemBatch::query()
                            ->where('sale_item_id', $saleItem->id)
                            ->where('product_batch_id', $batchId)
                            ->where('batch_no', $batchNo)
                            ->first()
                        : null;

                    if (! $sourceAllocation) {
                        throw ValidationException::withMessages([
                            "items.{$index}.batches.{$batchIndex}.batch_no" => [
                                'The batch was not allocated to this original sale item.',
                            ],
                        ]);
                    }
                }
            }

            if ($saleItem->product?->isSerialTracked()) {
                $serials = $item['serials'] ?? [];

                if ($serials === []) {
                    throw ValidationException::withMessages([
                        "items.{$index}.serials" => ['Serial numbers are required for this serial-tracked product.'],
                    ]);
                }

                $baseQuantity = (float) ($item['base_quantity'] ?? $qty);
                if (abs(count($serials) - round($baseQuantity)) > 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$index}.serials" => ['Serial count must match the returned base quantity.'],
                    ]);
                }

                foreach ($serials as $serialIndex => $serialValue) {
                    $serialNumber = is_array($serialValue)
                        ? ($serialValue['serial_number'] ?? $serialValue['serial_no'] ?? '')
                        : $serialValue;
                    $serialNumber = trim((string) $serialNumber);

                    if ($serialNumber === '') {
                        throw ValidationException::withMessages([
                            "items.{$index}.serials.{$serialIndex}" => ['A valid serial number is required.'],
                        ]);
                    }

                    if (isset($requestedSerialNumbers[$serialNumber])) {
                        throw ValidationException::withMessages([
                            "items.{$index}.serials.{$serialIndex}" => [
                                "Serial [{$serialNumber}] may only be returned once in this request.",
                            ],
                        ]);
                    }
                    $requestedSerialNumbers[$serialNumber] = true;

                    $sourceSerial = SaleItemSerial::query()
                        ->where('sale_id', $sale->id)
                        ->where('sale_item_id', $saleItem->id)
                        ->where('serial_number', $serialNumber)
                        ->lockForUpdate()
                        ->first();

                    if (! $sourceSerial) {
                        throw ValidationException::withMessages([
                            "items.{$index}.serials.{$serialIndex}" => [
                                "Serial [{$serialNumber}] was not allocated to this original sale item.",
                            ],
                        ]);
                    }

                    $alreadyReturnedSerial = SaleReturnItemSerial::query()
                        ->where('serial_number', $serialNumber)
                        ->whereHas('salesReturn', function ($q) use ($ignoreReturnId) {
                            $q->whereIn('status', [
                                SalesReturnStatus::DRAFT->value,
                                SalesReturnStatus::CONFIRMED->value,
                            ]);
                            if ($ignoreReturnId !== null) {
                                $q->where('id', '!=', $ignoreReturnId);
                            }
                        })
                        ->exists();

                    if ($alreadyReturnedSerial) {
                        throw ValidationException::withMessages([
                            "items.{$index}.serials.{$serialIndex}" => [
                                "Serial [{$serialNumber}] is already assigned to another sales return.",
                            ],
                        ]);
                    }
                }
            }
        }

        foreach ($batchQuantitiesBySaleItem as $saleItemId => $batchQuantities) {
            foreach ($batchQuantities as $batchId => $requestedQuantity) {
                $sourceAllocation = SaleItemBatch::query()
                    ->where('sale_item_id', $saleItemId)
                    ->where('product_batch_id', $batchId)
                    ->first();

                if (! $sourceAllocation) {
                    continue;
                }

                $alreadyReturned = (float) SaleReturnItemBatch::query()
                    ->where('product_batch_id', $batchId)
                    ->whereHas('salesReturnItem', fn ($q) => $q->where('sale_item_id', $saleItemId))
                    ->whereHas('salesReturn', function ($q) use ($ignoreReturnId) {
                        $q->whereIn('status', [
                            SalesReturnStatus::DRAFT->value,
                            SalesReturnStatus::CONFIRMED->value,
                        ]);
                        if ($ignoreReturnId !== null) {
                            $q->where('id', '!=', $ignoreReturnId);
                        }
                    })
                    ->sum('quantity');

                $maxReturnable = max(0, (float) $sourceAllocation->quantity - $alreadyReturned);
                if ($requestedQuantity > $maxReturnable + 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => [
                            "Batch {$sourceAllocation->batch_no} return quantity exceeds its remaining returnable quantity ({$maxReturnable}).",
                        ],
                    ]);
                }
            }
        }
    }

    public function validateRefundDetails(array $header): void
    {
        $refundType = $header['refund_type'] ?? SalesReturnRefundType::CREDIT_NOTE->value;

        if ($refundType instanceof SalesReturnRefundType) {
            $refundType = $refundType->value;
        }

        if ($refundType === SalesReturnRefundType::CREDIT_NOTE->value) {
            return;
        }

        if (empty($header['refund_account_id'])) {
            throw ValidationException::withMessages([
                'refund_account_id' => [
                    'A refund account is required for cash or bank Sales Returns.',
                ],
            ]);
        }

        $requiredRootCode = match ($refundType) {
            SalesReturnRefundType::CASH_REFUND->value => AccountingAccounts::CASH,
            SalesReturnRefundType::BANK_REFUND->value => AccountingAccounts::BANK,
            default => null,
        };

        $account = ChartOfAccount::query()->find($header['refund_account_id']);

        $rootAccount = $requiredRootCode !== null
            ? ChartOfAccount::query()
                ->where('account_code', $requiredRootCode)
                ->first()
            : null;

        if (
            ! $account
            || ! $rootAccount
            || ! $this->belongsToActiveAssetAccountBranch($account, $rootAccount)
        ) {
            $accountLabel = $refundType === SalesReturnRefundType::CASH_REFUND->value
                ? 'Cash'
                : 'Bank';

            throw ValidationException::withMessages([
                'refund_account_id' => [
                    "The selected refund account must be an active {$accountLabel} account or one of its subaccounts.",
                ],
            ]);
        }
    }

    private function belongsToActiveAssetAccountBranch(
        ChartOfAccount $account,
        ChartOfAccount $rootAccount
    ): bool {
        $visited = [];

        while ($account) {
            $accountId = (int) $account->id;

            if (
                isset($visited[$accountId])
                || ! $account->is_active
                || $account->account_type !== AccountType::ASSET
            ) {
                return false;
            }

            if ($accountId === (int) $rootAccount->id) {
                return true;
            }

            $visited[$accountId] = true;

            if ($account->parent_id === null) {
                return false;
            }

            $account = ChartOfAccount::query()->find($account->parent_id);

            if (! $account) {
                return false;
            }
        }

        return false;
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

        $items = $return->items()
            ->with(['batches', 'serials'])
            ->get()
            ->map(fn ($item) => [
                'sale_item_id' => $item->sale_item_id,
                'product_id' => $item->product_id,
                'returned_quantity' => (float) $item->returned_quantity,
                'base_quantity' => (float) $item->base_quantity,
                'batches' => $item->batches->map(fn ($batch) => [
                    'product_batch_id' => $batch->product_batch_id,
                    'batch_no' => $batch->batch_no,
                    'quantity' => (float) $batch->quantity,
                ])->all(),
                'serials' => $item->serials->map(fn ($serial) => [
                    'serial_number' => $serial->serial_number,
                ])->all(),
            ])
            ->all();

        $this->validateForSave(
            [
                'sale_id' => $return->sale_id,
                'customer_id' => $return->customer_id,
                'refund_type' => $return->refund_type,
                'refund_account_id' => $return->refund_account_id,
            ],
            $items,
            $return->id
        );
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
