<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Supplier\Models\Supplier;
use App\Modules\Accounting\Repositories\Contracts\PayableSummaryRepositoryInterface;

class PayableSummaryRepository implements PayableSummaryRepositoryInterface
{
    public function getReport(): array
    {
        return Supplier::query()
            ->withSum('purchases as total_purchase', 'grand_total')
            ->withSum('purchases as total_paid', 'paid_amount')
            ->withSum('purchases as outstanding', 'due_amount')
            ->get()
            ->map(function (Supplier $supplier) {
                return [
                    'supplier_id' => $supplier->id,
                    'supplier_uuid' => $supplier->uuid,
                    'supplier_name' => $supplier->name,
                    'total_purchase' => (float) ($supplier->total_purchase ?? 0),
                    'total_paid' => (float) ($supplier->total_paid ?? 0),
                    'outstanding' => (float) ($supplier->outstanding ?? 0),
                ];
            })
            ->filter(function ($row) {
                return $row['total_purchase'] > 0
                    || $row['total_paid'] > 0
                    || $row['outstanding'] > 0;
            })
            ->values()
            ->toArray();
    }
}
