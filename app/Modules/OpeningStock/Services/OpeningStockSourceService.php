<?php

namespace App\Modules\OpeningStock\Services;

use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\OpeningStock\Models\OpeningStockSource;
use App\Modules\OpeningStock\Repositories\Contracts\OpeningStockSourceRepositoryInterface;

class OpeningStockSourceService
{
    public function __construct(
        protected OpeningStockSourceRepositoryInterface $repository,
        protected OpeningStockItemService $itemService,
    ) {}

    public function create(
        OpeningStock $openingStock,
        array $data
    ): OpeningStockSource {
        $items = $data['items'] ?? [];

        unset($data['items']);

        $source = $this->repository->create([
            'opening_stock_id' => $openingStock->id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'bill_no' => $data['bill_no'],
            'bill_date' => $data['bill_date'],
            'remarks' => $data['remarks'] ?? null,
        ]);

        $this->itemService->createItems(
            source: $source,
            items: $items
        );

        return $source;
    }

    public function createSources(
        OpeningStock $openingStock,
        array $sources
    ): void {
        foreach ($sources as $source) {
            $this->create(
                openingStock: $openingStock,
                data: $source
            );
        }
    }

    public function deleteSources(
        OpeningStock $openingStock
    ): void {
        $openingStock->loadMissing(
            'sources.items'
        );

        foreach ($openingStock->sources as $source) {
            $this->itemService->deleteItems(
                source: $source
            );
        }

        $this->repository->deleteByOpeningStock(
            $openingStock->id
        );
    }

    public function replaceSources(
        OpeningStock $openingStock,
        array $sources
    ): void {
        $this->deleteSources(
            openingStock: $openingStock
        );

        $this->createSources(
            openingStock: $openingStock,
            sources: $sources
        );
    }
}