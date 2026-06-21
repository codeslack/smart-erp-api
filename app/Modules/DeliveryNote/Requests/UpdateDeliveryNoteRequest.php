<?php

namespace App\Modules\DeliveryNote\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'delivery_date' => [
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
