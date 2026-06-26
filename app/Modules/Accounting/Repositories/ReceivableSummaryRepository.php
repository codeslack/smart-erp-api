<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Customer\Models\Customer;
use App\Modules\Accounting\Repositories\Contracts\ReceivableSummaryRepositoryInterface;

class ReceivableSummaryRepository
    implements ReceivableSummaryRepositoryInterface
{
    public function getReport(): array
    {
        return Customer::query()

            ->get()

            ->map(function (
                Customer $customer
            ) {

                $totalSales = $customer
                    ->sales()
                    ->sum(
                        'grand_total'
                    );

                $totalReceived = $customer
                    ->sales()
                    ->sum(
                        'paid_amount'
                    );

                $outstanding =
                    $customer
                    ->sales()
                    ->sum(
                        'due_amount'
                    );

                return [

                    'customer_id'
                        => $customer->id,

                    'customer_name'
                        => $customer->name,

                    'total_sales'
                        => (float) $totalSales,

                    'total_received'
                        => (float) $totalReceived,

                    'outstanding'
                        => (float) $outstanding,
                ];
            })

            ->filter(function ($row) {
                return $row['total_sales'] > 0
                    || $row['total_received'] > 0
                    || $row['outstanding'] > 0;
            })

            ->values()

            ->toArray();
    }
}