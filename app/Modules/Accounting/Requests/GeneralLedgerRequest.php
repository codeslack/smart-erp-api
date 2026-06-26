<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;

class GeneralLedgerRequest
    extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'account_id' => [

                'required',

                TenantRule::exists(
                    'chart_of_accounts'
                ),
            ],

            'from_date' => [

                'nullable',
                'date',
            ],

            'to_date' => [

                'nullable',
                'date',
                'after_or_equal:from_date',
            ],
        ];
    }
}
