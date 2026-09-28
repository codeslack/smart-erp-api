<?php

namespace App\Modules\Receipt\Repositories;

use Illuminate\Validation\ValidationException;

use App\Core\Repositories\BaseRepository;

use App\Modules\Receipt\Enums\ReceiptStatusEnum;

use App\Modules\Receipt\Models\Receipt;

use App\Modules\Receipt\Repositories\Contracts\ReceiptRepositoryInterface;

class ReceiptRepository
    extends BaseRepository
    implements ReceiptRepositoryInterface
{
    public function __construct(
        Receipt $model
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

    public function delete(int|string $id)
    {
        $receipt = $this->find($id);

        if (
            $receipt->status ===
            ReceiptStatusEnum::CONFIRMED
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
