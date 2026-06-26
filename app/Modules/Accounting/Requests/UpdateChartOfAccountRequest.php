<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Accounting\Enums\AccountType;

class UpdateChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $account = $this->route(
            'chartOfAccount'
        );

        return [

            'account_group_id' => [
                'required',
            ],

            'parent_id' => [
                'nullable',
            ],

            'account_code' => [
                'required',
                'string',
                'max:50',

                Rule::unique(
                    'chart_of_accounts',
                    'account_code'
                )
                ->where(
                    'tenant_id',
                    tenant()->id
                )
                ->ignore(
                    $account?->id
                ),
            ],

            'account_name' => [
                'required',
                'string',
                'max:255',
            ],

            'account_type' => [
                'required',
                'in:' . implode(
                    ',',
                    AccountType::values()
                ),
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }
}