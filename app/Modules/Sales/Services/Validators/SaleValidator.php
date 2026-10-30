<?php

namespace App\Modules\Sales\Services\Validators;

use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\Sales\Models\Sale;
use Illuminate\Validation\ValidationException;

class SaleValidator
{
    public function validateForUpdate(Sale $sale): void
    {
        $status = $sale->status instanceof SaleStatusEnum
            ? $sale->status
            : SaleStatusEnum::tryFrom((string) $sale->status);

        if ($status !== SaleStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales can be updated.'],
            ]);
        }
    }

    public function validateForConfirmation(Sale $sale): void
    {
        $status = $sale->status instanceof SaleStatusEnum
            ? $sale->status
            : SaleStatusEnum::tryFrom((string) $sale->status);

        if ($status !== SaleStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales can be confirmed.'],
            ]);
        }

        if ($sale->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Cannot confirm a sale without items.'],
            ]);
        }
    }

    public function validateForCancellation(Sale $sale): void
    {
        $status = $sale->status instanceof SaleStatusEnum
            ? $sale->status
            : SaleStatusEnum::tryFrom((string) $sale->status);

        if (! in_array($status, [SaleStatusEnum::DRAFT, SaleStatusEnum::CONFIRMED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel a sale that is in ' . ($status?->label() ?? 'current') . ' status.'],
            ]);
        }
    }

    public function validateForDeletion(Sale $sale): void
    {
        $status = $sale->status instanceof SaleStatusEnum
            ? $sale->status
            : SaleStatusEnum::tryFrom((string) $sale->status);

        if ($status !== SaleStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales can be deleted.'],
            ]);
        }
    }
}
