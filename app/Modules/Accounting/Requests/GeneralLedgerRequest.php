<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;

class GeneralLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => [
                'required_without:account_uuid',
                'nullable',
                TenantRule::exists(
                    'chart_of_accounts',
                    'id'
                ),
            ],

            'account_uuid' => [
                'required_without:account_id',
                'nullable',
                TenantRule::exists(
                    'chart_of_accounts',
                    'uuid'
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
