<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Supplier\Models\Supplier;
use App\Modules\Accounting\Repositories\Contracts\PayableSummaryRepositoryInterface;

class PayableSummaryRepository
    implements PayableSummaryRepositoryInterface
{
    public function getReport(): array
    {
        return Supplier::query()

            ->get()

            ->map(function (
                Supplier $supplier
            ) {

                $totalPurchase = $supplier
                    ->purchases()
                    ->sum(
                        'grand_total'
                    );

                $totalPaid = $supplier
                    ->purchases()
                    ->sum(
                        'paid_amount'
                    );

                $outstanding = $supplier
                    ->purchases()
                    ->sum(
                        'due_amount'
                    );

                return [

                    'supplier_id'
                        => $supplier->id,

                    'supplier_name'
                        => $supplier->name,

                    'total_purchase'
                        => (float) $totalPurchase,

                    'total_paid'
                        => (float) $totalPaid,

                    'outstanding'
                        => (float) $outstanding,
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