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
        return DB::transaction(function () use ($data) {

            $stock = ProductStock::firstOrCreate(
                [
                    'tenant_id' => tenant()->id,
                    'product_id' => $data['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                ],
                [
                    'quantity' => 0,
                ]
            );

            $newBalance =
                $stock->quantity +
                $data['quantity'];

            StockLedger::create([
                'tenant_id' => tenant()->id,

                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],

                'transaction_type' => StockTransactionType::OPENING_STOCK,

                'qty_in' => $data['quantity'],
                'qty_out' => 0,

                'balance_after' => $newBalance,

                'remarks' => $data['remarks'] ?? null,
            ]);

            $stock->update([
                'quantity' => $newBalance,
            ]);

            return $stock->fresh();
        });
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
}