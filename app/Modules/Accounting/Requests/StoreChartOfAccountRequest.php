<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;
use App\Modules\Accounting\Enums\AccountType;

class StoreChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'account_group_id' => [
                'required',
                TenantRule::exists(
                    'account_groups'
                ),
            ],

            'parent_id' => [
                'nullable',
                TenantRule::exists(
                    'chart_of_accounts'
                ),
            ],

            'account_code' => [
                'required',
                'string',
                'max:50',

                TenantRule::unique(
                    'chart_of_accounts',
                    'account_code'
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

            'is_system' => [
                'boolean',
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }
}