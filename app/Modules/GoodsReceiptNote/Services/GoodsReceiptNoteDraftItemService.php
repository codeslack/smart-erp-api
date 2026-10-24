<?php

namespace App\Modules\GoodsReceiptNote\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemRepositoryInterface;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteRepositoryInterface;
use App\Modules\GoodsReceiptNote\Rules\GoodsReceiptNoteRules;

use App\Modules\ProductUnit\Services\ProductUnitService;
use App\Modules\ProductUnit\Repositories\Contracts\ProductUnitRepositoryInterface;

class GoodsReceiptNoteDraftItemService
{
    public function __construct(
        protected GoodsReceiptNoteItemRepositoryInterface $items,
        protected GoodsReceiptNoteRepositoryInterface $grns,
        protected ProductUnitRepositoryInterface $productUnits,
        protected ProductUnitService $productUnitService,
        protected GoodsReceiptNoteRules $rules,
    ) {}

    public function updateItem(
        GoodsReceiptNote $grn,
        GoodsReceiptNoteItem $item,
        array $data
    ): GoodsReceiptNoteItem {
        return DB::transaction(function () use ($grn, $item, $data) {
            $grn = $this->lockDraftGrn($grn);
            $item = $this->lockItem($grn, $item);

            $this->rules->ensureDraft($grn);

            $quantity = array_key_exists('quantity_received', $data)
                ? (float) $data['quantity_received']
                : (float) $item->quantity_received;

            $unitCost = array_key_exists('unit_cost', $data)
                ? (float) $data['unit_cost']
                : (float) $item->unit_cost;

            $this->rules->ensurePositiveQuantity($quantity);
            $this->rules->ensureNonNegativeAmount($unitCost);

            $productUnit = $this->productUnits->findByProductAndUnit(
                (int) $item->product_id,
                (int) $item->unit_id
            );

            if (! $productUnit) {
                throw new BusinessException(
                    'The GRN item unit is not configured for this product.'
                );
            }

            $this->productUnitService->ensurePurchaseUnit($productUnit);

            $baseQuantity = $this->productUnitService->toBaseQuantity(
                $productUnit,
                $quantity
            );

            $baseUnitCost = $this->productUnitService->toBaseUnitCost(
                $productUnit,
                $unitCost
            );

            $this->ensureTrackingFitsQuantity(
                $item,
                $baseQuantity
            );

            $changes = [
                'quantity_received' => $quantity,
                'base_quantity' => $baseQuantity,
                'unit_cost' => $unitCost,
                'base_unit_cost' => $baseUnitCost,
                'line_total' => round($quantity * $unitCost, 4),
            ];

            foreach (
                [
                    'tax_group_id',
                    'tax_rate',
                    'tax_amount',
                    'remarks',
                ] as $field
            ) {
                if (array_key_exists($field, $data)) {
                    $changes[$field] = $data[$field];
                }
            }

            $this->items->update($item, $changes);

            $this->refreshTotalValuation($grn);

            return $this->items->findWithTracking($item->id)
                ?? $item->refresh();
        });
    }

    public function deleteItem(
        GoodsReceiptNote $grn,
        GoodsReceiptNoteItem $item
    ): void {
        DB::transaction(function () use ($grn, $item) {
            $grn = $this->lockDraftGrn($grn);
            $item = $this->lockItem($grn, $item);

            $this->rules->ensureDraft($grn);

            // Explicitly delete tracking rows first so deletion does
            // not depend on database cascade configuration.
            $item->batches()->delete();
            $item->serials()->delete();

            $this->items->delete($item);

            $this->refreshTotalValuation($grn);

            if (! $grn->items()->exists()) {
                // Empty drafts are permitted after item removal.
                // Confirmation should enforce that at least one item exists.
                $this->grns->update($grn, [
                    'total_received_valuation' => 0,
                    'updated_by' => userId(),
                ]);
            }
        });
    }

    protected function lockDraftGrn(
        GoodsReceiptNote $grn
    ): GoodsReceiptNote {
        $locked = GoodsReceiptNote::query()
            ->whereKey($grn->getKey())
            ->lockForUpdate()
            ->first();

        if (! $locked) {
            throw new BusinessException(
                'Goods receipt note not found.'
            );
        }

        $this->rules->ensureDraft($locked);

        return $locked;
    }

    protected function lockItem(
        GoodsReceiptNote $grn,
        GoodsReceiptNoteItem $item
    ): GoodsReceiptNoteItem {
        $locked = $this->items->findLockedForGoodsReceiptNote(
            $item->getKey(),
            $grn->getKey()
        );

        if (! $locked) {
            throw new BusinessException(
                'Goods receipt note item not found.'
            );
        }

        $locked->load([
            'batches',
            'serials',
        ]);

        return $locked;
    }

    protected function ensureTrackingFitsQuantity(
        GoodsReceiptNoteItem $item,
        float $baseQuantity
    ): void {
        $batchQuantity = round(
            (float) $item->batches->sum(
                fn($batch) => (float) $batch->quantity
            ),
            4
        );

        if ($batchQuantity > round($baseQuantity, 4)) {
            throw new BusinessException(
                'Updated quantity cannot be lower than the total batch quantity.'
            );
        }

        $serialCount = $item->serials->count();

        if ($serialCount > round($baseQuantity, 4)) {
            throw new BusinessException(
                'Updated quantity cannot be lower than the existing serial count.'
            );
        }

        if ($serialCount > 0) {
            $this->rules->ensureWholeQuantity($baseQuantity);
        }
    }

    protected function refreshTotalValuation(
        GoodsReceiptNote $grn
    ): void {
        $total = round(
            $grn->items()
                ->sum('line_total'),
            4
        );

        $this->grns->update($grn, [
            'total_received_valuation' => $total,
        ]);
    }
}
