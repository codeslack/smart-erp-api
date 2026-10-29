<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\ChartOfAccount;

class UpdateChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $account = $this->route('chartOfAccount') ?? $this->route('chart_of_account');
        $id = $account instanceof ChartOfAccount
            ? $account->id
            : (is_numeric($account) ? $account : ChartOfAccount::where('uuid', $account)->value('id'));

        return [
            'account_group_id' => [
                'nullable',
                TenantRule::exists('account_groups', 'id'),
            ],

            'account_group_uuid' => [
                'nullable',
                TenantRule::exists('account_groups', 'uuid'),
            ],

            'parent_id' => [
                'nullable',
                TenantRule::exists('chart_of_accounts', 'id'),
            ],

            'parent_account_uuid' => [
                'nullable',
                TenantRule::exists('chart_of_accounts', 'uuid'),
            ],

            'account_code' => [
                'required',
                'string',
                'max:50',
                TenantRule::unique(
                    'chart_of_accounts',
                    'account_code'
                )->ignore($id),
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
