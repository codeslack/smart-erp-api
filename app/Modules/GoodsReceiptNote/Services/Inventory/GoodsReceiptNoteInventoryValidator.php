<?php

namespace App\Modules\GoodsReceiptNote\Services\Inventory;

use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;

class GoodsReceiptNoteInventoryValidator
{
    public function validate(
        GoodsReceiptNoteItem $item
    ): void {
        if (! $item->product) {
            throw ValidationException::withMessages([
                'items' => [
                    'A GRN item references a missing product.',
                ],
            ]);
        }

        if ((float) $item->base_quantity <= 0) {
            throw ValidationException::withMessages([
                'items' => [
                    'Base quantity must be greater than zero.',
                ],
            ]);
        }

        if ((float) $item->base_unit_cost < 0) {
            throw ValidationException::withMessages([
                'items' => [
                    'Base unit cost cannot be negative.',
                ],
            ]);
        }

        if ($item->product->isBatchTracked()) {
            $batchQuantity = (float) $item->batches->sum(
                'quantity'
            );

            if (
                $item->batches->isEmpty()
                || abs(
                    $batchQuantity - (float) $item->base_quantity
                ) > 0.0001
            ) {
                throw ValidationException::withMessages([
                    'items' => [
                        'Batch quantities must equal the item base quantity.',
                    ],
                ]);
            }

            $item->loadMissing('goodsReceiptNote');

            foreach ($item->batches as $grnBatch) {
                if ((float) $grnBatch->quantity <= 0) {
                    throw ValidationException::withMessages([
                        'batches' => [
                            'Each batch quantity must be greater than zero.',
                        ],
                    ]);
                }

                if (trim((string) $grnBatch->batch_no) === '') {
                    throw ValidationException::withMessages([
                        'batches' => [
                            'Batch numbers cannot be empty.',
                        ],
                    ]);
                }

                if (
                    $item->product->hasExpiryTracking()
                    && $grnBatch->expiry_date === null
                ) {
                    throw ValidationException::withMessages([
                        'batches' => [
                            "An expiry date is required for batch {$grnBatch->batch_no}.",
                        ],
                    ]);
                }

                $grnDate = $item->goodsReceiptNote?->grn_date;

                if (
                    $grnBatch->expiry_date !== null
                    && $grnDate !== null
                    && $grnBatch->expiry_date->lt(
                        Carbon::parse($grnDate)->startOfDay()
                    )
                ) {
                    throw ValidationException::withMessages([
                        'batches' => [
                            "Batch {$grnBatch->batch_no} was expired on the GRN date.",
                        ],
                    ]);
                }
            }
        }

        if ($item->product->isSerialTracked()) {
            $baseQuantity = (float) $item->base_quantity;
            $wholeQuantity = round($baseQuantity);

            if (
                abs($baseQuantity - $wholeQuantity) > 0.0001
                || $item->serials->count() !== (int) $wholeQuantity
            ) {
                throw ValidationException::withMessages([
                    'items' => [
                        'Serial-tracked items require one serial per whole base unit.',
                    ],
                ]);
            }

            $serialNumbers = $item->serials->map(
                fn ($serial) => trim(
                    (string) $serial->serial_number
                )
            );

            if (
                $serialNumbers->contains('')
                || $serialNumbers->unique()->count()
                    !== $serialNumbers->count()
            ) {
                throw ValidationException::withMessages([
                    'serials' => [
                        'Serial numbers must be non-empty and unique within each GRN item.',
                    ],
                ]);
            }
        }
    }
}