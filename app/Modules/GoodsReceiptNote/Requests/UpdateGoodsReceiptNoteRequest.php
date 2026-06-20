<?php

namespace App\Modules\GoodsReceiptNote\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoodsReceiptNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'received_date' => [
                'sometimes',
                'date',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

        ];
    }
}
