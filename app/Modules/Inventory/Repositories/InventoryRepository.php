<?php

namespace App\Modules\Inventory\Repositories;

use Illuminate\Support\Facades\DB;
use App\Modules\Product\Models\Product;
use App\Modules\Inventory\Models\StockLedger;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Enums\StockTransactionType;
use App\Modules\Inventory\Repositories\Contracts\InventoryRepositoryInterface;

class InventoryRepository implements InventoryRepositoryInterface
{
    public function openingStock(array $data)
    {
        return $this->stockIn(
            productId: $data['product_id'],
            warehouseId: $data['warehouse_id'],
            quantity: $data['quantity'],
            unitCost: $data['unit_cost'],
            transactionType: StockTransactionType::OPENING_STOCK,
            remarks: $data['remarks'] ?? null,
        );
    }

    public function stock(Product $product)
    {
        return ProductStock::query()
            ->where('product_id', $product->id)
            ->get();
    }

    public function ledger(Product $product)
    {
        return StockLedger::query()
            ->where('product_id', $product->id)
            ->latest('id')
            ->get();
    }

    public function stockIn(
        int $productId,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        string $transactionType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    ) {
        return DB::transaction(function () use (
            $productId,
            $warehouseId,
            $quantity,
            $unitCost,
            $transactionType,
            $referenceType,
            $referenceId,
            $remarks
        ) {

            $stock = ProductStock::firstOrCreate(
                [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'quantity' => 0,
                    'average_cost' => 0,
                ]
            );

            $oldQty = $stock->quantity;

            $oldCost = $stock->average_cost;

            $newQty = $oldQty + $quantity;

            $newAverageCost = $newQty > 0
                ? (
                    (($oldQty * $oldCost)
                        +
                        ($quantity * $unitCost))
                    / $newQty
                )
                : 0;

            $stock->update([
                'quantity' => $newQty,
                'average_cost' => $newAverageCost,
            ]);

            StockLedger::create([
                'product_id' => $productId,

                'warehouse_id' => $warehouseId,

                'transaction_type' => $transactionType,

                'reference_type' => $referenceType,

                'reference_id' => $referenceId,

                'qty_in' => $quantity,

                'qty_out' => 0,

                'unit_cost' => $unitCost,
                'line_cost' => $quantity * $unitCost,

                'balance_after' => $newQty,

                'remarks' => $remarks,
            ]);

            return $stock->fresh();
        });
    }

    public function stockOut(
        int $productId,
        int $warehouseId,
        float $quantity,
        string $transactionType,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    ) {
        return DB::transaction(function () use (
            $productId,
            $warehouseId,
            $quantity,
            $transactionType,
            $referenceType,
            $referenceId,
            $remarks
        ) {

            $stock = ProductStock::query()
                ->lockForUpdate()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if (! $stock) {
                abort(422, 'Stock not found.');
            }

            if ($stock->quantity < $quantity) {
                abort(
                    422,
                    sprintf(
                        'Insufficient stock. Available: %s',
                        $stock->quantity
                    )
                );
            }

            $averageCost = $stock->average_cost;

            $lineCost = $quantity * $averageCost;

            $newBalance = $stock->quantity - $quantity;

            $stock->update([
                'quantity' => $newBalance,
            ]);

            StockLedger::create([
                'tenant_id' => tenant()->id,

                'product_id' => $productId,

                'warehouse_id' => $warehouseId,

                'transaction_type' => $transactionType,

                'reference_type' => $referenceType,

                'reference_id' => $referenceId,

                'qty_in' => 0,

                'qty_out' => $quantity,

                'unit_cost' => $averageCost,

                'line_cost' => $lineCost,

                'balance_after' => $newBalance,

                'remarks' => $remarks,
            ]);

            return $stock->fresh();
        });
    }
}
