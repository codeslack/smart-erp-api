<?php

namespace App\Modules\SupplierPayment\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Validation\ValidationException;
use App\Modules\SupplierPayment\Models\SupplierPayment;
use App\Modules\SupplierPayment\Enums\SupplierPaymentStatus;
use App\Modules\SupplierPayment\Repositories\Contracts\SupplierPaymentRepositoryInterface;

class SupplierPaymentRepository
    extends BaseRepository
    implements SupplierPaymentRepositoryInterface
{
    public function __construct(
        SupplierPayment $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function paginate(int $perPage = 15)
    {
        return $this->model
            ->with([
                'supplier',
                'paymentAccount',
            ])
            ->withCount('allocations')
            ->latest()
            ->paginate($perPage);
    }

    public function find(
        int|string $id
    )
    {
        return $this->model
            ->with([
                'supplier',
                'paymentAccount',
                'allocations.purchase',
            ])
            ->findOrFail(
                $id
            );
    }

    public function delete( int|string $id )
    {
        $payment = $this->find($id);

        if (
            $payment->status ===
                SupplierPaymentStatus::CONFIRMED
        ) {
            throw ValidationException::withMessages([
                'payment' => [
                    'Confirmed payments cannot be deleted.'
                ]
            ]);
        }

        return $payment->delete();
    }
}