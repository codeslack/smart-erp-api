<?php

namespace App\Modules\Accounting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashFlowResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'operating_activities'
                => $this['operating_activities'],

            'investing_activities'
                => $this['investing_activities'],

            'financing_activities'
                => $this['financing_activities'],

            'net_cash_flow'
                => $this['net_cash_flow'],

            'opening_cash'
                => $this['opening_cash'],

            'closing_cash'
                => $this['closing_cash'],
        ];
    }
}