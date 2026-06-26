<?php

namespace App\Modules\Accounting\Repositories;

use Carbon\Carbon;
use App\Modules\Sales\Models\Sale;
use App\Modules\Accounting\Repositories\Contracts\CustomerAgingRepositoryInterface;

class CustomerAgingRepository
    implements CustomerAgingRepositoryInterface
{
    public function getReport(
        ?string $asOfDate = null
    ): array {

        $asOfDate = $asOfDate
            ? Carbon::parse($asOfDate)
            : now();

        $sales = Sale::query()

            ->with('customer')

            ->where(
                'tenant_id',
                tenant()->id
            )

            ->where(
                'due_amount',
                '>',
                0
            )

            ->get();

        $report = [];

        foreach ($sales as $sale) {

            $customerId = $sale->customer_id;

            if (! isset($report[$customerId])) {

                $report[$customerId] = [

                    'customer_id'
                        => $sale->customer_id,

                    'customer_name'
                        => $sale->customer->name ?? 'Unknown Customer',

                    'current'
                        => 0,

                    'days_31_60'
                        => 0,

                    'days_61_90'
                        => 0,

                    'days_91_120'
                        => 0,

                    'days_120_plus'
                        => 0,

                    'total_due'
                        => 0,
                ];
            }

            $age = Carbon::parse(
                $sale->sale_date
            )->diffInDays(
                $asOfDate
            );

            $amount = (float) $sale->due_amount;

            if ($age <= 30) {

                $report[$customerId]['current']
                    += $amount;

            } elseif ($age <= 60) {

                $report[$customerId]['days_31_60']
                    += $amount;

            } elseif ($age <= 90) {

                $report[$customerId]['days_61_90']
                    += $amount;

            } elseif ($age <= 120) {

                $report[$customerId]['days_91_120']
                    += $amount;

            } else {

                $report[$customerId]['days_120_plus']
                    += $amount;
            }

            $report[$customerId]['total_due']
                += $amount;
        }

        return array_values(
            $report
        );
    }
}

