<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'voucher_type' => [
                'required',
                'string',
            ],

            'entry_date' => [
                'required',
                'date',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'reference_type' => [
                'nullable',
                'string',
            ],

            'reference_id' => [
                'nullable',
                'integer',
            ],

            'lines' => [
                'required',
                'array',
                'min:2',
            ],

            'lines.*.chart_of_account_id' => [
                'required',
                TenantRule::exists(
                    'chart_of_accounts'
                ),
            ],

            'lines.*.debit' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'lines.*.credit' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'lines.*.description' => [
                'nullable',
                'string',
            ],
        ];
    }
}
