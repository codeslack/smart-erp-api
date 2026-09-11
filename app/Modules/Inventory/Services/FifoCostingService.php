<?php

namespace App\Modules\Inventory\Services;

use App\Core\Exceptions\BusinessException;
use Illuminate\Support\Collection;

use App\Modules\Inventory\Enums\InventoryCostLayerStatusEnum;
use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Models\InventoryCostLayer;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerConsumptionRepositoryInterface;

class FifoCostingService
{
    public function __construct(
        protected InventoryCostLayerRepositoryInterface $repository,
        protected InventoryCostLayerConsumptionRepositoryInterface $consumptionRepository
    ) {}

    public function createLayer(
        InventoryMovementData $movement,
        int $stockLedgerId
    ): InventoryCostLayer {
        if ($movement->quantity <= 0) {
            throw new BusinessException(
                'FIFO layer quantity must be greater than zero.'
            );
        }

        if ($movement->unitCost < 0) {
            throw new BusinessException(
                'FIFO layer unit cost cannot be negative.'
            );
        }

        return $this->repository->create([
            'product_id' => $movement->productId,
            'product_variant_id' => $movement->productVariantId,
            'warehouse_id' => $movement->warehouseId,
            'stock_ledger_id' => $stockLedgerId,
            'original_quantity' => $movement->quantity,
            'remaining_quantity' => $movement->quantity,
            'unit_cost' => $movement->unitCost,
            'layer_date' => $movement->transactionDate,
            'status' => InventoryCostLayerStatusEnum::OPEN->value,
        ]);
    }

    public function consume(
        InventoryMovementData $movement
    ): Collection {
        if ($movement->quantity <= 0) {
            throw new BusinessException(
                'FIFO consumption quantity must be greater than zero.'
            );
        }

        $layers = $this->repository->availableLayers(
            productId: $movement->productId,
            productVariantId: $movement->productVariantId,
            warehouseId: $movement->warehouseId
        );

        $remaining = (string) $movement->quantity;
        $consumed = collect();

        foreach ($layers as $layer) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            $available = (string) $layer->remaining_quantity;

            if (bccomp($available, '0', 4) <= 0) {
                continue;
            }

            $consumeQuantity = bccomp(
                $available,
                $remaining,
                4
            ) <= 0
                ? $available
                : $remaining;

            $unitCost = (string) $layer->unit_cost;

            $totalCost = bcmul(
                $consumeQuantity,
                $unitCost,
                4
            );

            $newRemaining = bcsub(
                $available,
                $consumeQuantity,
                4
            );

            $status = bccomp(
                $newRemaining,
                '0',
                4
            ) === 0
                ? InventoryCostLayerStatusEnum::EXHAUSTED->value
                : InventoryCostLayerStatusEnum::OPEN->value;

            $this->repository->update(
                $layer,
                [
                    'remaining_quantity' => $newRemaining,
                    'status' => $status,
                ]
            );

            $consumed->push([
                'layer' => $layer,
                'layer_id' => $layer->id,
                'quantity' => (float) $consumeQuantity,
                'unit_cost' => (float) $unitCost,
                'total_cost' => (float) $totalCost,
            ]);

            $remaining = bcsub(
                $remaining,
                $consumeQuantity,
                4
            );
        }

        if (bccomp($remaining, '0', 4) > 0) {
            throw new BusinessException(sprintf(
                'Insufficient FIFO stock layers. Remaining quantity: %s',
                number_format(
                    (float) $remaining,
                    4,
                    '.',
                    ''
                )
            ));
        }

        return $consumed;
    }

    public function recordConsumptions(
        int $stockLedgerId,
        Collection $consumptions
    ): Collection {
        if ($stockLedgerId <= 0) {
            throw new BusinessException(
                'Stock ledger ID must be greater than zero.'
            );
        }

        if ($consumptions->isEmpty()) {
            throw new BusinessException(
                'FIFO consumption history cannot be empty.'
            );
        }

        return $this->consumptionRepository->createMany(
            stockLedgerId: $stockLedgerId,
            consumptions: $consumptions
        );
    }

    public function consumeAndCalculateCost(
        InventoryMovementData $movement
    ): array {
        $layers = $this->consume($movement);

        return [
            'layers' => $layers,
            'total_cost' => (float) $layers->sum('total_cost'),
            'quantity' => (float) $movement->quantity,
        ];
    }
}