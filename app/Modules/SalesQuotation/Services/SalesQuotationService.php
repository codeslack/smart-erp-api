<?php

namespace App\Modules\SalesQuotation\Services;

use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Repositories\Contracts\SalesQuotationRepositoryInterface;
use App\Modules\SalesQuotation\Services\Actions\AcceptSalesQuotationAction;
use App\Modules\SalesQuotation\Services\Actions\CancelSalesQuotationAction;
use App\Modules\SalesQuotation\Services\Actions\ConvertSalesQuotationToOrderAction;
use App\Modules\SalesQuotation\Services\Actions\CreateSalesQuotationAction;
use App\Modules\SalesQuotation\Services\Actions\RejectSalesQuotationAction;
use App\Modules\SalesQuotation\Services\Actions\SendSalesQuotationAction;
use App\Modules\SalesQuotation\Services\Actions\UpdateSalesQuotationAction;
use App\Modules\SalesQuotation\Services\Validators\SalesQuotationValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SalesQuotationService
{
    public function __construct(
        protected SalesQuotationRepositoryInterface $repository,
        protected CreateSalesQuotationAction $createAction,
        protected UpdateSalesQuotationAction $updateAction,
        protected SendSalesQuotationAction $sendAction,
        protected AcceptSalesQuotationAction $acceptAction,
        protected RejectSalesQuotationAction $rejectAction,
        protected CancelSalesQuotationAction $cancelAction,
        protected ConvertSalesQuotationToOrderAction $convertOrderAction,
        protected SalesQuotationValidator $validator
    ) {}

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateWithFilters($filters, $perPage);
    }

    public function find(int|string $id): SalesQuotation
    {
        return $this->repository->find($id);
    }

    public function findByUuid(string $uuid): ?SalesQuotation
    {
        return $this->repository->findByUuid($uuid);
    }

    public function create(array $data): SalesQuotation
    {
        return $this->createAction->execute($data);
    }

    public function update(int|string $id, array $data): SalesQuotation
    {
        $quotation = $this->resolveQuotation($id);

        return $this->updateAction->execute($quotation, $data);
    }

    public function send(int|string $id): SalesQuotation
    {
        $quotation = $this->resolveQuotation($id);

        return $this->sendAction->execute($quotation);
    }

    public function accept(int|string $id): SalesQuotation
    {
        $quotation = $this->resolveQuotation($id);

        return $this->acceptAction->execute($quotation);
    }

    public function reject(int|string $id, ?string $reason = null): SalesQuotation
    {
        $quotation = $this->resolveQuotation($id);

        return $this->rejectAction->execute($quotation, $reason);
    }

    public function cancel(int|string $id, ?string $reason = null): SalesQuotation
    {
        $quotation = $this->resolveQuotation($id);

        return $this->cancelAction->execute($quotation, $reason);
    }

    public function convertToOrder(int|string $id, array $overrides = []): SalesOrder
    {
        $quotation = $this->resolveQuotation($id);

        return $this->convertOrderAction->execute($quotation, $overrides);
    }

    public function delete(int|string $id): bool
    {
        $quotation = $this->resolveQuotation($id);
        $this->validator->validateForDeletion($quotation);

        $quotation->items()->delete();

        return (bool) $this->repository->delete($quotation);
    }

    public function getItems(int|string $id): Collection
    {
        $quotation = $this->resolveQuotation($id);

        return $quotation->items()->with([
            'product',
            'variant',
            'warehouse',
            'unit',
            'tax',
        ])->get();
    }

    protected function resolveQuotation(int|string $id): SalesQuotation
    {
        if (is_string($id) && ! is_numeric($id)) {
            $quotation = $this->repository->findByUuid($id);
            if ($quotation) {
                return $quotation;
            }
        }

        return $this->repository->find($id);
    }
}
