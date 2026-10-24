<?php

namespace App\Modules\GoodsReceiptNote\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

class UpdateGoodsReceiptNoteItemRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'quantity_received' => [
                'sometimes',
                'numeric',
                'gt:0',
            ],

            'unit_cost' => [
                'sometimes',
                'numeric',
                'gte:0',
            ],

            'tax_group_id' => [
                'sometimes',
                'nullable',
                'integer',
                TenantRule::exists('tax_groups'),
            ],

            'tax_rate' => [
                'sometimes',
                'numeric',
                'gte:0',
            ],

            'tax_amount' => [
                'sometimes',
                'numeric',
                'gte:0',
            ],

            'remarks' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }
}