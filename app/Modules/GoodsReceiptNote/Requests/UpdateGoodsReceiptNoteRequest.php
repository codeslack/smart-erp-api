<?php

namespace App\Modules\GoodsReceiptNote\Requests;

use App\Core\Requests\BaseRequest;

class UpdateGoodsReceiptNoteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'grn_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'delivery_challan_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'delivery_challan_date' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'vehicle_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'transporter_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],

            'remarks' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}