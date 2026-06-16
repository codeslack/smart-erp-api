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

            $stock = ProductStock::firstOrCreate(
                [
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                ],
                [
                    'quantity' => 0,
                ]
            );

            $newBalance =
                $stock->quantity +
                $quantity;

            $stock->update([
                'quantity' => $newBalance,
            ]);

            StockLedger::create([
                'product_id' => $productId,

                'warehouse_id' => $warehouseId,

                'transaction_type' => $transactionType,

                'reference_type' => $referenceType,

                'reference_id' => $referenceId,

                'qty_in' => $quantity,

                'qty_out' => 0,

                'balance_after' => $newBalance,

                'remarks' => $remarks,
            ]);

            return $stock->fresh();
        });
    }
}