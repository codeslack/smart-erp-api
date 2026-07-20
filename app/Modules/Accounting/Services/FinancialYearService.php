<?php

namespace App\Modules\Accounting\Services;

use Carbon\Carbon;

class FinancialYearService
{
    public function current(): string
    {
        $today = Carbon::today();

        if ($today->month >= 4) {

            $startYear =
                $today->year;

            $endYear =
                $today->year + 1;
        } else {

            $startYear =
                $today->year - 1;

            $endYear =
                $today->year;
        }

        return sprintf(
            '%s-%s',
            $startYear,
            substr(
                (string) $endYear,
                -2
            )
        );
    }
}