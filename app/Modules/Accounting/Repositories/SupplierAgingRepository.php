<?php

namespace App\Modules\Accounting\Repositories;

use Carbon\Carbon;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Accounting\Repositories\Contracts\SupplierAgingRepositoryInterface;

class SupplierAgingRepository
    implements SupplierAgingRepositoryInterface
{
    public function getReport(
        ?string $asOfDate = null
    ): array {

        $asOfDate = $asOfDate
            ? Carbon::parse($asOfDate)
            : now();

        $purchases = Purchase::query()

            ->with('supplier')

            ->where(
                'due_amount',
                '>',
                0
            )

            ->get();

        $report = [];

        foreach ($purchases as $purchase) {

            $supplierId = $purchase->supplier_id;

            if (! isset($report[$supplierId])) {

                $report[$supplierId] = [

                    'supplier_id'
                        => $purchase->supplier_id,

                    'supplier_name'
                        => $purchase->supplier?->name ?? 'Unknown Supplier',

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
                $purchase->purchase_date
            )->diffInDays(
                $asOfDate
            );

            $amount = (float) $purchase->due_amount;

            if ($age <= 30) {

                $report[$supplierId]['current']
                    += $amount;

            } elseif ($age <= 60) {

                $report[$supplierId]['days_31_60']
                    += $amount;

            } elseif ($age <= 90) {

                $report[$supplierId]['days_61_90']
                    += $amount;

            } elseif ($age <= 120) {

                $report[$supplierId]['days_91_120']
                    += $amount;

            } else {

                $report[$supplierId]['days_120_plus']
                    += $amount;
            }

            $report[$supplierId]['total_due']
                += $amount;
        }

        return array_values(
            $report
        );
    }
}

