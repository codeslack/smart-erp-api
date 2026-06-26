<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Validation\TenantRule;

class StoreAccountGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'name' => [
                'required',
                'string',
                'max:255',
                TenantRule::unique(
                    'account_groups',
                    'name'
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