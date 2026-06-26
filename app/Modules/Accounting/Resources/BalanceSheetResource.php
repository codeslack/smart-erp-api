<?php

namespace App\Modules\Accounting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceSheetResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'assets'
                => $this['assets'],

            'liabilities'
                => $this['liabilities'],

            'equities'
                => $this['equities'],

            'total_assets'
                => $this['total_assets'],

            'total_liabilities'
                => $this['total_liabilities'],

            'total_equity'
                => $this['total_equity'],

            'total_liabilities_and_equity'
                => $this['total_liabilities_and_equity'],

            'is_balanced'
                => $this['is_balanced'],
        ];
    }
}