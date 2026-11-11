<?php

namespace App\Modules\SalesOrder\Services\Validators;

use App\Modules\SalesOrder\Enums\SalesOrderStatusEnum;
use App\Modules\SalesOrder\Models\SalesOrder;
use Illuminate\Validation\ValidationException;

class SalesOrderValidator
{
    public function validateForUpdate(SalesOrder $order): void
    {
        $status = $order->status instanceof SalesOrderStatusEnum
            ? $order->status
            : SalesOrderStatusEnum::tryFrom((string) $order->status);

        if ($status !== SalesOrderStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales orders can be updated.'],
            ]);
        }
    }

    public function validateForConfirmation(SalesOrder $order): void
    {
        $status = $order->status instanceof SalesOrderStatusEnum
            ? $order->status
            : SalesOrderStatusEnum::tryFrom((string) $order->status);

        if ($status !== SalesOrderStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales orders can be confirmed.'],
            ]);
        }

        if ($order->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Cannot confirm a sales order without items.'],
            ]);
        }
    }

    public function validateForCancellation(SalesOrder $order): void
    {
        $status = $order->status instanceof SalesOrderStatusEnum
            ? $order->status
            : SalesOrderStatusEnum::tryFrom((string) $order->status);

        if (! in_array($status, [SalesOrderStatusEnum::DRAFT, SalesOrderStatusEnum::CONFIRMED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel a sales order that is ' . ($status?->label() ?? 'in current status') . '.'],
            ]);
        }

        // Cannot cancel if partially delivered or delivered
        $delivered = (float) $order->items()->sum('delivered_quantity');
        if ($delivered > 0) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel a sales order with delivered quantities.'],
            ]);
        }

        $invoiced = (float) $order->items()->sum('invoiced_quantity');
        if ($invoiced > 0) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel a sales order with invoiced quantities.'],
            ]);
        }
    }

    public function validateForDeletion(SalesOrder $order): void
    {
        $status = $order->status instanceof SalesOrderStatusEnum
            ? $order->status
            : SalesOrderStatusEnum::tryFrom((string) $order->status);

        if ($status !== SalesOrderStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales orders can be deleted.'],
            ]);
        }
    }
}
