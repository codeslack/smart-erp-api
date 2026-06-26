<?php

namespace App\Modules\Accounting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrialBalanceResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'accounts'
                => $this['accounts'],

            'total_debit'
                => $this['total_debit'],

            'total_credit'
                => $this['total_credit'],

            'is_balanced'
                => $this['is_balanced'],
        ];
    }
}
