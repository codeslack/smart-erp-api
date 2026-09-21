<?php

namespace App\Modules\OpeningStock\Controllers;

use Illuminate\Http\JsonResponse;

use App\Http\Controllers\ApiController;

use App\Modules\OpeningStock\Models\OpeningStock;

use App\Modules\OpeningStock\Services\OpeningStockService;

use App\Modules\OpeningStock\Resources\OpeningStockResource;

use App\Modules\OpeningStock\Requests\StoreOpeningStockRequest;
use App\Modules\OpeningStock\Requests\UpdateOpeningStockRequest;

class OpeningStockController extends ApiController
{
    public function __construct(
        protected OpeningStockService $service,
    ) {}

    public function index(): JsonResponse
    {
        $openingStocks = OpeningStock::query()
            ->with([
                'warehouse',
                'sources.supplier',
                'sources.items.product',
                'sources.items.variant',
                'sources.items.batch',
                'sources.items.serial',
            ])
            ->latest('id')
            ->paginate();

        return $this->success(
            data: OpeningStockResource::collection($openingStocks),
            message: 'Opening stocks retrieved successfully.',
        );
    }

    public function store(
        StoreOpeningStockRequest $request,
    ): JsonResponse {
        $openingStock = $this->service->create(
            $request->validated(),
        );

        return $this->success(
            data: new OpeningStockResource(
                $openingStock->load([
                    'warehouse',
                    'sources.supplier',
                    'sources.items.product',
                    'sources.items.variant',
                    'sources.items.batch',
                    'sources.items.serial',
                ]),
            ),
            message: 'Opening stock created successfully.',
            status: 201,
        );
    }

    public function show(
        OpeningStock $openingStock,
    ): JsonResponse {
        $openingStock->load([
            'warehouse',
            'sources.supplier',
            'sources.items.product',
            'sources.items.variant',
            'sources.items.batch',
            'sources.items.serial',
        ]);

        return $this->success(
            data: new OpeningStockResource($openingStock),
        );
    }

    public function update(
        UpdateOpeningStockRequest $request,
        OpeningStock $openingStock,
    ): JsonResponse {
        $openingStock = $this->service->update(
            $openingStock,
            $request->validated(),
        );

        return $this->success(
            data: new OpeningStockResource(
                $openingStock->load([
                    'warehouse',
                    'sources.supplier',
                    'sources.items.product',
                    'sources.items.variant',
                    'sources.items.batch',
                    'sources.items.serial',
                ]),
            ),
            message: 'Opening stock updated successfully.',
        );
    }

    public function destroy(
        OpeningStock $openingStock,
    ): JsonResponse {
        $this->service->delete($openingStock);

        return $this->success(
            message: 'Opening stock deleted successfully.',
        );
    }

    public function approve(
        OpeningStock $openingStock,
    ): JsonResponse {
        
        $openingStock = $this->service->approve($openingStock);

        return $this->success(
            data: new OpeningStockResource(
                $openingStock->load([
                    'warehouse',
                    'sources.supplier',
                    'sources.items.product',
                    'sources.items.variant',
                    'sources.items.batch',
                    'sources.items.serial',
                ]),
            ),
            message: 'Opening stock approved successfully.',
        );
    }
        
}

