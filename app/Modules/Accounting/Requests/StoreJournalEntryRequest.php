<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Validation\Rules\Enum;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;

class StoreJournalEntryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            'voucher_type' => [
                'required',
                new Enum(JournalVoucherTypeEnum::class),
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

            'lines.*.chart_of_account_uuid' => [
                'required',
                TenantRule::exists(
                    'chart_of_accounts',
                    'uuid'
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
