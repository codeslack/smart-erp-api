<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $group = $this->route(
            'accountGroup'
        );

        return [

            'name' => [
                'required',
                'string',
                'max:255',

                Rule::unique(
                    'account_groups',
                    'name'
                )
                    ->where(
                        'tenant_id',
                        tenant()->id
                    )
                    ->ignore(
                        $group?->id
                    ),
            ],

            'code' => [
                'nullable',
                'string',
                'max:50',
            ],
        ];
    }
}
