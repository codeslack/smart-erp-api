<?php

namespace App\Modules\Inventory\Repositories;

use DomainException;
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
            ->where('tenant_id', tenant()->id)
            ->where('product_id', $product->id)
            ->get();
    }

    public function ledger(Product $product)
    {
        return StockLedger::query()
            ->where('tenant_id', tenant()->id)
            ->where('product_id', $product->id)
            ->latest('id')
            ->get();
    }

    public function stockReport(
        array $filters = []
    ) {

        $query = ProductStock::query()
            ->with([
                'product',
                'warehouse',
            ]);

        if (
            ! empty($filters['product_id'])
        ) {
            $query->where(
                'product_id',
                $filters['product_id']
            );
        }

        if (
            ! empty($filters['warehouse_id'])
        ) {
            $query->where(
                'warehouse_id',
                $filters['warehouse_id']
            );
        }

        return $query
            ->get()
            ->map(function ($stock) {

                return [

                    'product_id'
                        => $stock->product_id,

                    'product_name'
                        => $stock->product->name,

                    'sku'
                        => $stock->product->sku,

                    'warehouse_id'
                        => $stock->warehouse_id,

                    'warehouse_name'
                        => $stock->warehouse->name,

                    'quantity'
                        => $stock->quantity,

                    'average_cost'
                        => $stock->average_cost,

                    'stock_value'
                        => bcmul(
                            $stock->quantity,
                            $stock->average_cost,
                            4
                        ),
                ];
            });
    }    

    public function availableStock(
        int $productId,
        int $warehouseId
    ): float {

        return (float) ProductStock::query()
            ->where('tenant_id', tenant()->id)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity');
    }

    public function averageCost(
        int $productId,
        int $warehouseId
    ): float {

        return (float) ProductStock::query()
            ->where('tenant_id', tenant()->id)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->value('average_cost');
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

            $stock = ProductStock::query()
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'tenant_id'    => tenant()->id,
                        'product_id'   => $productId,
                        'warehouse_id' => $warehouseId,
                    ],
                    [
                        'quantity'     => 0,
                        'average_cost' => 0,
                    ]
                );

            $oldQuantity = (float) $stock->quantity;
            $oldCost = (float) $stock->average_cost;

            $newQuantity = $oldQuantity + $quantity;

            $newAverageCost = $newQuantity > 0
                ? (
                    (($oldQuantity * $oldCost)
                        +
                        ($quantity * $unitCost))
                    / $newQuantity
                )
                : 0;

            $stock->update([
                'quantity'     => $newQuantity,
                'average_cost' => $newAverageCost,
            ]);

            StockLedger::create([
                'tenant_id'        => tenant()->id,
                'product_id'       => $productId,
                'warehouse_id'     => $warehouseId,
                'transaction_type' => $transactionType,
                'reference_type'   => $referenceType,
                'reference_id'     => $referenceId,
                'qty_in'           => $quantity,
                'qty_out'          => 0,
                'unit_cost'        => $unitCost,
                'line_cost'        => $quantity * $unitCost,
                'balance_after'    => $newQuantity,
                'remarks'          => $remarks,
            ]);

            return $stock->fresh();
        });
    }

    public function stockOut(
        int $productId,
        int $warehouseId,
        float $quantity,
        string $transactionType,
        ?float $unitCost = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $remarks = null
    ) {
        return DB::transaction(function () use (
            $productId,
            $warehouseId,
            $quantity,
            $transactionType,
            $unitCost,
            $referenceType,
            $referenceId,
            $remarks
        ) {

            $stock = ProductStock::query()
                ->lockForUpdate()
                ->where('tenant_id', tenant()->id)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if (! $stock) {
                throw new DomainException(
                    'Stock record not found.'
                );
            }

            if (bccomp(
                (string) $stock->quantity,
                (string) $quantity,
                4
            ) < 0) {

                throw new DomainException(
                    sprintf(
                        'Insufficient stock. Available: %s, Requested: %s',
                        $stock->quantity,
                        $quantity
                    )
                );
            }

            $cost = $unitCost ?? (float) $stock->average_cost;

            $lineCost = $quantity * $cost;

            $newQuantity = (float) $stock->quantity - $quantity;

            $stock->update([
                'quantity' => $newQuantity,
            ]);

            StockLedger::create([
                'tenant_id'        => tenant()->id,
                'product_id'       => $productId,
                'warehouse_id'     => $warehouseId,
                'transaction_type' => $transactionType,
                'reference_type'   => $referenceType,
                'reference_id'     => $referenceId,
                'qty_in'           => 0,
                'qty_out'          => $quantity,
                'unit_cost'        => $cost,
                'line_cost'        => $lineCost,
                'balance_after'    => $newQuantity,
                'remarks'          => $remarks,
            ]);

            return $stock->fresh();
        });
    }
}
