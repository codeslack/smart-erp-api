<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DayBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'from_date' => [

                'nullable',
                'date',
            ],

            'to_date' => [

                'nullable',
                'date',
                'after_or_equal:from_date',
            ],
        ];
    }
}
