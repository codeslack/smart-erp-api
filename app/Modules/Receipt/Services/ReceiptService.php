<?php

namespace App\Modules\Receipt\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\Sales\Models\Sale;

use Illuminate\Validation\ValidationException;

use App\Modules\Receipt\Models\Receipt;
use App\Modules\Receipt\Models\ReceiptAllocation;

use App\Modules\Receipt\Enums\ReceiptStatusEnum;

use App\Modules\Receipt\Repositories\Contracts\ReceiptRepositoryInterface;

use App\Modules\Receipt\Validation\ReceiptValidator;

use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;

class ReceiptService
{
    public function __construct(
        protected ReceiptRepositoryInterface $repository,
        protected ReceiptValidator $validator,
        protected AccountingPostingServiceInterface $postingService,
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(
        int|string $id
    ): Receipt {

        return $this->repository->find(
            $id
        );
    }

    public function create(
        array $data
    ): Receipt {

        return DB::transaction(
            function () use ($data) {

                $allocations =
                    $data['allocations'] ?? [];

                unset(
                    $data['allocations']
                );

                $this->validator->validate(
                    $data,
                    $allocations
                );

                $receiptNo = nextDocumentNumber(
                    'receipt',
                    'REC'
                );

                $receipt =
                    $this->repository->create([

                        ...$data,

                        'receipt_no' => $receiptNo,

                        'status' =>
                        ReceiptStatusEnum::DRAFT,
                    ]);

                $this->createAllocations(
                    $receipt,
                    $allocations
                );

                return $this->find(
                    $receipt->id
                );
            }
        );
    }

    public function update(
        int|string $id,
        array $data
    ): Receipt {

        return DB::transaction(
            function () use (
                $id,
                $data
            ) {

                $receipt =
                    $this->find($id);

                $this->ensureDraft(
                    $receipt
                );

                $allocations =
                    $data['allocations'] ?? [];

                unset(
                    $data['allocations']
                );

                $this->validator->validate(
                    $data,
                    $allocations
                );

                $receipt->update(
                    $data
                );

                $receipt
                    ->allocations()
                    ->delete();

                $this->createAllocations(
                    $receipt,
                    $allocations
                );

                return $this->find(
                    $receipt->id
                );
            }
        );
    }

    public function confirm(
        Receipt $receipt
    ): Receipt {

        return DB::transaction(
            function () use ($receipt) {

                $receipt =
                    Receipt::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $receipt->id
                    );

                $this->ensureDraft(
                    $receipt
                );

                foreach (
                    $receipt->allocations as $allocation
                ) {

                    $sale =
                        Sale::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $allocation->sale_id
                        );

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

                    'status' =>
                    ReceiptStatusEnum::CONFIRMED,
                ]);

                $receipt =
                    $this->find(
                        $receipt->id
                    );

                $this->postingService
                    ->postReceipt(
                        $receipt
                    );

                return $receipt;
            }
        );
    }

    public function cancel(
        Receipt $receipt
    ): Receipt {

        $this->ensureDraft(
            $receipt
        );

        $receipt->update([

            'status' =>
            ReceiptStatusEnum::CANCELLED,
        ]);

        return $receipt->fresh();
    }

    public function delete(
        int|string $id
    ): bool {

        $receipt =
            $this->find($id);

        $this->ensureDraft(
            $receipt
        );

        return (bool)
        $this->repository
            ->delete($id);
    }

    protected function createAllocations(
        Receipt $receipt,
        array $allocations
    ): void {

        foreach (
            $allocations as $allocation
        ) {

            ReceiptAllocation::create([

                'tenant_id' =>
                tenant()->id,

                'receipt_id' =>
                $receipt->id,

                'sale_id' =>
                $allocation['sale_id'],

                'allocated_amount' =>
                $allocation['allocated_amount'],
            ]);
        }
    }

    protected function ensureDraft(
        Receipt $receipt
    ): void {

        if (
            $receipt->status !==
            ReceiptStatusEnum::DRAFT
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    'Only draft receipts can be modified.'
                ]
            ]);
        }
    }
}
