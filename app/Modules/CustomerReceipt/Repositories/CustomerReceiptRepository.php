<?php

namespace App\Modules\CustomerReceipt\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Validation\ValidationException;
use App\Modules\CustomerReceipt\Models\CustomerReceipt;
use App\Modules\CustomerReceipt\Enums\CustomerReceiptStatus;
use App\Modules\CustomerReceipt\Repositories\Contracts\CustomerReceiptRepositoryInterface;

class CustomerReceiptRepository 
    extends BaseRepository
    implements CustomerReceiptRepositoryInterface
{
    public function __construct(
        CustomerReceipt $model
    ) {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 15)
    {
        return $this->model
            ->with([
                'customer',
                'paymentAccount',
            ])
            ->withCount('allocations')
            ->latest()
            ->paginate($perPage);
    }

    public function find(int|string $id)
    {
        return $this->model
            ->with([
                'customer',
                'paymentAccount',
                'allocations.sale',
            ])
            ->findOrFail($id);
    }

    public function delete( int|string $id )
    {
        $receipt = $this->find($id);

        if (
            $receipt->status ===
                CustomerReceiptStatus::CONFIRMED
        ) {
            throw ValidationException::withMessages([
                'receipt' => [
                    'Confirmed receipts cannot be deleted.'
                ]
            ]);
        }

        return $receipt->delete();
    }
}