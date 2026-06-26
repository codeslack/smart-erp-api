<?php

namespace App\Modules\Accounting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfitLossResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'income_accounts'
                => $this['income_accounts'],

            'expense_accounts'
                => $this['expense_accounts'],

            'total_income'
                => $this['total_income'] ?? 0,

            'total_expense'
                => $this['total_expense'] ?? 0,

            'net_profit'
                => $this['net_profit'] ?? 0,
        ];
    }
}
