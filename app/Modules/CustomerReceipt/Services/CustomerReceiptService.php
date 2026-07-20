<?php

namespace App\Modules\CustomerReceipt\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\Sales\Models\Sale;

use Illuminate\Validation\ValidationException;

use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\CustomerReceipt\Models\CustomerReceiptAllocation;

use App\Modules\CustomerReceipt\Enums\CustomerReceiptStatus;

use App\Modules\CustomerReceipt\Repositories\Contracts\CustomerReceiptRepositoryInterface;

use App\Modules\CustomerReceipt\Validation\CustomerReceiptValidator;

use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;

class CustomerReceiptService
{
    public function __construct(
        protected CustomerReceiptRepositoryInterface $repository,
        protected CustomerReceiptValidator $validator,
        protected AccountingPostingServiceInterface $postingService,
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(
        int|string $id
    ): CustomerReceipt {

        return $this->repository->find(
            $id
        );
    }

    public function create(
        array $data
    ): CustomerReceipt {

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
                    'customer_receipt',
                    'REC'
                );

                $receipt =
                    $this->repository->create([

                        ...$data,

                        'receipt_no' => $receiptNo,

                        'status' =>
                            CustomerReceiptStatus::DRAFT,
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
    ): CustomerReceipt {

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
        CustomerReceipt $receipt
    ): CustomerReceipt {

        return DB::transaction(
            function () use ($receipt) {

                $receipt =
                    CustomerReceipt::query()
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
                        CustomerReceiptStatus::CONFIRMED,
                ]);

                $receipt =
                    $this->find(
                        $receipt->id
                    );

                $this->postingService
                    ->postCustomerReceipt(
                        $receipt
                    );

                return $receipt;
            }
        );
    }

    public function cancel(
        CustomerReceipt $receipt
    ): CustomerReceipt {

        $this->ensureDraft(
            $receipt
        );

        $receipt->update([

            'status' =>
                CustomerReceiptStatus::CANCELLED,
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
        CustomerReceipt $receipt,
        array $allocations
    ): void {

        foreach (
            $allocations as $allocation
        ) {

            CustomerReceiptAllocation::create([

                'tenant_id' =>
                    tenant()->id,

                'customer_receipt_id' =>
                    $receipt->id,

                'sale_id' =>
                    $allocation['sale_id'],

                'allocated_amount' =>
                    $allocation['allocated_amount'],
            ]);
        }
    }

    protected function ensureDraft(
        CustomerReceipt $receipt
    ): void {

        if (
            $receipt->status !==
            CustomerReceiptStatus::DRAFT
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    'Only draft receipts can be modified.'
                ]
            ]);
        }
    }
}
