<?php

namespace App\Modules\GoodsReceiptNote\Rules;

use App\Core\Exceptions\BusinessException;

use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;

class GoodsReceiptNoteRules
{
    public function ensureDraft(GoodsReceiptNote $grn): void
    {
        if ($grn->status !== GoodsReceiptNoteStatusEnum::DRAFT) {
            throw new BusinessException(
                'Only draft goods receipt notes can be modified.'
            );
        }
    }

    public function ensureCanConfirm(GoodsReceiptNote $grn): void
    {
        if ($grn->status !== GoodsReceiptNoteStatusEnum::DRAFT) {
            throw new BusinessException(
                'Only draft goods receipt notes can be confirmed.'
            );
        }
    }

    public function ensureCanCancel(GoodsReceiptNote $grn): void
    {
        if ($grn->status !== GoodsReceiptNoteStatusEnum::CONFIRMED) {
            throw new BusinessException(
                'Only confirmed goods receipt notes can be cancelled.'
            );
        }
    }

    public function ensurePositiveQuantity(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new BusinessException(
                'Received quantity must be greater than zero.'
            );
        }
    }

    public function ensureNonNegativeAmount(float $amount): void
    {
        if ($amount < 0) {
            throw new BusinessException(
                'Amount cannot be negative.'
            );
        }
    }

    public function ensureBatchQuantityMatches(
        GoodsReceiptNoteItem $item,
        float $batchQuantity
    ): void {
        if (
            round((float) $item->base_quantity, 4)
            !== round($batchQuantity, 4)
        ) {
            throw new BusinessException(
                'Total batch quantity must match the received base quantity.'
            );
        }
    }

    public function ensureSerialCountMatches(
        GoodsReceiptNoteItem $item,
        int $serialCount
    ): void {
        if (
            ! $this->isWholeQuantity((float) $item->base_quantity)
        ) {
            throw new BusinessException(
                'Serial-tracked products must have a whole-number quantity.'
            );
        }

        if (
            (int) round((float) $item->base_quantity)
            !== $serialCount
        ) {
            throw new BusinessException(
                'Serial count must match the received base quantity.'
            );
        }
    }

    public function ensureWholeQuantity(float $quantity): void
    {
        if (! $this->isWholeQuantity($quantity)) {
            throw new BusinessException(
                'Serial-tracked products must have a whole-number quantity.'
            );
        }
    }

    public function ensureItemHasTrackingData(
        GoodsReceiptNoteItem $item
    ): void {
        $product = $item->product;

        if (! $product) {
            throw new BusinessException(
                'Product not found for GRN item.'
            );
        }

        if ($product->isBatchTracked()) {
            $this->ensureBatchQuantityMatches(
                $item,
                (float) $item->batches->sum(
                    fn ($batch) => (float) $batch->quantity
                )
            );
        }

        if ($product->isSerialTracked()) {
            $this->ensureSerialCountMatches(
                $item,
                $item->serials->count()
            );
        }
    }

    private function isWholeQuantity(float $quantity): bool
    {
        return abs($quantity - round($quantity)) < 0.00000001;
    }
}