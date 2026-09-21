<?php

namespace App\Modules\OpeningStock\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Enums\DocumentStatusEnum;
use App\Core\Exceptions\BusinessException;

use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\OpeningStock\Repositories\Contracts\OpeningStockRepositoryInterface;

class OpeningStockService
{
    public function __construct(
        protected OpeningStockRepositoryInterface $repository,
        protected OpeningStockSourceService $sourceService,
        protected OpeningStockTotalService $totalService,
        protected OpeningStockInventoryPostingService $postingService,
    ) {}

    public function create(
        array $data
    ): OpeningStock {
        return DB::transaction(
            function () use ($data) {

                $sources = $data['sources'] ?? [];

                unset($data['sources']);

                $data['document_no'] = nextDocumentNumber(
                    'opening_stock',
                    'OPN'
                );

                $data['status'] =
                    DocumentStatusEnum::DRAFT->value;

                $openingStock =
                    $this->repository->create(
                        $data
                    );

                $this->sourceService->createSources(
                    $openingStock,
                    $sources
                );

                $this->totalService->recalculate(
                    $openingStock
                );

                return $this->loadBasicRelations(
                    $openingStock->fresh()
                );
            }
        );
    }

    public function update(
        OpeningStock $openingStock,
        array $data
    ): OpeningStock {
        return DB::transaction(
            function () use (
                $openingStock,
                $data
            ) {

                $this->ensureDraft(
                    $openingStock
                );

                $sources = $data['sources'] ?? [];

                unset($data['sources']);

                $openingStock =
                    $this->repository->update(
                        $openingStock,
                        $data
                    );

                $this->sourceService->replaceSources(
                    $openingStock,
                    $sources
                );

                $this->totalService->recalculate(
                    $openingStock
                );

                return $this->loadBasicRelations(
                    $openingStock->fresh()
                );
            }
        );
    }

    public function approve(
        OpeningStock $openingStock
    ): OpeningStock {
        return DB::transaction(
            function () use ($openingStock) {

                $this->ensureDraft(
                    $openingStock
                );

                $this->postingService->post(
                    $openingStock
                );

                return $this->loadFullRelations(
                    $openingStock->fresh()
                );
            }
        );
    }

    public function unapprove(
        OpeningStock $openingStock
    ): OpeningStock {
        return DB::transaction(
            function () use ($openingStock) {

                if (
                    $openingStock->status
                    !== DocumentStatusEnum::CONFIRMED->value
                ) {
                    throw new BusinessException(
                        'Only confirmed opening stock can be unapproved.'
                    );
                }

                $this->postingService->unpost(
                    $openingStock
                );

                return $this->loadFullRelations(
                    $openingStock->fresh()
                );
            }
        );
    }

    public function delete(
        OpeningStock $openingStock
    ): bool {
        return DB::transaction(
            function () use ($openingStock) {

                $this->ensureDraft(
                    $openingStock
                );

                $this->sourceService->deleteSources(
                    openingStock: $openingStock
                );

                return $this->repository->delete(
                    $openingStock
                );
            }
        );
    }

    protected function ensureDraft(
        OpeningStock $openingStock
    ): void {
        if (
            $openingStock->status
            !== DocumentStatusEnum::DRAFT->value
        ) {
            throw new BusinessException(
                'Only draft opening stock can be modified.'
            );
        }
    }

    protected function loadBasicRelations(
        OpeningStock $openingStock
    ): OpeningStock {
        return $openingStock->load([
            'sources.items',
        ]);
    }

    protected function loadFullRelations(
        OpeningStock $openingStock
    ): OpeningStock {
        return $openingStock->load([
            'warehouse',
            'sources.supplier',
            'sources.items.product',
            'sources.items.variant',
            'sources.items.batch',
            'sources.items.serial',
        ]);
    }
}
