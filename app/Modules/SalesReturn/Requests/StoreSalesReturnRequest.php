<?php

namespace App\Modules\SalesReturn\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;
use App\Modules\SalesReturn\Enums\SalesReturnCondition;
use App\Modules\SalesReturn\Enums\SalesReturnRefundType;
use Illuminate\Validation\Rule;

class StoreSalesReturnRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'return_date' => ['required', 'date'],

            'sale_id' => [
                'required_without:sale_uuid',
                'nullable',
                'integer',
                TenantRule::exists('sales', 'id'),
            ],
            'sale_uuid' => [
                'required_without:sale_id',
                'nullable',
                'string',
                TenantRule::exists('sales', 'uuid'),
            ],

            'refund_account_id' => [
                'nullable',
                'integer',
                TenantRule::exists('chart_of_accounts', 'id'),
            ],
            'refund_account_uuid' => [
                'nullable',
                'string',
                TenantRule::exists('chart_of_accounts', 'uuid'),
            ],

            'return_no' => ['nullable', 'string', 'max:50'],
            'refund_type' => ['nullable', Rule::enum(SalesReturnRefundType::class)],
            'return_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*' => [
                'required',
                'array:sale_item_id,sale_item_uuid,returned_quantity,quantity,condition,reason,batches,serials',
            ],

            'items.*.sale_item_id' => [
                'required_without:items.*.sale_item_uuid',
                'nullable',
                'integer',
                TenantRule::exists('sale_items', 'id'),
            ],
            'items.*.sale_item_uuid' => [
                'required_without:items.*.sale_item_id',
                'nullable',
                'string',
                TenantRule::exists('sale_items', 'uuid'),
            ],

            'items.*.returned_quantity' => [
                'required_without:items.*.quantity',
                'nullable',
                'numeric',
                'gt:0',
            ],
            'items.*.quantity' => [
                'required_without:items.*.returned_quantity',
                'nullable',
                'numeric',
                'gt:0',
            ],

            'items.*.condition' => ['nullable', Rule::enum(SalesReturnCondition::class)],
            'items.*.reason' => ['nullable', 'string', 'max:255'],

            'items.*.batches' => ['nullable', 'array'],
            'items.*.batches.*' => [
                'required',
                'array:batch_no,quantity',
            ],
            'items.*.batches.*.batch_no' => [
                'required_with:items.*.batches',
                'string',
                'max:100',
            ],
            'items.*.batches.*.quantity' => [
                'required_with:items.*.batches',
                'numeric',
                'gt:0',
            ],

            'items.*.serials' => ['nullable', 'array'],
        ];
    }
}
