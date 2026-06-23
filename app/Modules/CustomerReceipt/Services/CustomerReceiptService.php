<?php

namespace App\Modules\CustomerReceipt\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Sales\Models\Sale;
use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\CustomerReceipt\Enums\CustomerReceiptStatus;
use App\Modules\CustomerReceipt\Models\CustomerReceiptAllocation;
use App\Modules\CustomerReceipt\Repositories\Contracts\CustomerReceiptRepositoryInterface;

class CustomerReceiptService
{
    public function __construct(
        protected CustomerReceiptRepositoryInterface $repository
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(int $id)
    {
        return $this->repository->find(
            $id
        );
    }

    public function create(
        array $data
    ) {
        return DB::transaction(function () use ($data) {

            $allocations = $data['allocations'];

            unset(
                $data['allocations']
            );

            $nextId = (
                CustomerReceipt::max('id') ?? 0
            ) + 1;

            $data['receipt_no'] = sprintf(
                'REC-%06d',
                $nextId
            );

            $data['status'] = CustomerReceiptStatus::DRAFT;

            $receipt = $this->repository->create(
                $data
            );

            foreach (
                $allocations as $allocation
            ) {

                $sale = Sale::findOrFail(
                    $allocation['sale_id']
                );

                abort_if(
                    $allocation['allocated_amount']
                        >
                        $sale->due_amount,
                    422,
                    'Allocated amount exceeds invoice due amount.'
                );

                CustomerReceiptAllocation::create([
                    'tenant_id'          => tenant()->id,
                    'customer_receipt_id' => $receipt->id,
                    'sale_id'            => $sale->id,
                    'allocated_amount'   => $allocation['allocated_amount'],
                ]);
            }

            return $receipt->load(
                'allocations.sale'
            );
        });
    }

    public function confirm(
        CustomerReceipt $receipt
    ) {
        return DB::transaction(function () use ($receipt) {

            abort_if(
                $receipt->status !== CustomerReceiptStatus::DRAFT,
                422,
                'Only draft receipts can be confirmed.'
            );

            $receipt->loadMissing(
                'allocations.sale'
            );

            foreach (
                $receipt->allocations as $allocation
            ) {

                $sale = $allocation->sale;

                $sale->increment(
                    'paid_amount',
                    $allocation->allocated_amount
                );

                $sale->decrement(
                    'due_amount',
                    $allocation->allocated_amount
                );
            }

            $receipt->update([
                'status' => CustomerReceiptStatus::CONFIRMED,
            ]);

            return $receipt->fresh(
                'allocations.sale'
            );
        });
    }

    public function cancel(
        CustomerReceipt $receipt
    ) {
        abort_if(
            $receipt->status !== CustomerReceiptStatus::DRAFT,
            422,
            'Only draft receipts can be cancelled.'
        );

        $receipt->update([
            'status' => CustomerReceiptStatus::CANCELLED,
        ]);

        return $receipt->fresh();
    }

    public function delete(
        int $id
    ) {
        $receipt = $this->find(
            $id
        );

        abort_if(
            $receipt->status !== CustomerReceiptStatus::DRAFT,
            422,
            'Only draft receipts can be deleted.'
        );

        return $this->repository->delete(
            $id
        );
    }
}
