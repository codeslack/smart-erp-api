<?php

namespace App\Modules\Inventory\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Modules\Product\Models\Product;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Requests\OpeningStockRequest;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $service
    ) {}

    /**
     * POST /api/inventory/opening-stock
     */
    public function openingStock(
        OpeningStockRequest $request
    ) {
        $stock = $this->service
            ->openingStock(
                $request->validated()
            );

        return response()->json([
            'success' => true,
            'message' => 'Opening stock added successfully',
            'data' => $stock,
        ]);
    }

    /**
     * GET /api/inventory/products/{product}/stock
     * Returns current stock.
     */
    public function stock(
        Product $product
    ) {
        $stock = $this->service
            ->stock(
                $product
            );
        
        return response()->json([
            'success' => true,
            'message' => 'Current stock retrieved successfully',
            'data' => $stock,
        ]);

    }

    /**
     * GET /api/inventory/products/{product}/ledger
     * Stock History
     */
    public function ledger(
        Product $product
    ){
        $ledger = $this->service
            ->ledger(
                $product
            );

        return response()->json([
            'success' => true,
            'message' => 'Current stock ledger retrieved successfully',
            'data' => $ledger,
        ]);
    }

    /**
     * GET /api/inventory/stock-report
     * Stock report
     */
    public function stockReport(
        Request $request
    ) {
        return response()->json([
            'success' => true,
            'message' => 'Current stock report retrieved successfully',
            'data' => $this->service->stockReport(
                $request->all()
            ),
        ]);
    }    

}