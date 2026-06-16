<?php

namespace App\Modules\Unit\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = tenant()?->id;
        
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('units', 'name')
                    ->where('tenant_id', $tenantId)
            ],

            'short_name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('units', 'short_name')
                    ->where('tenant_id', $tenantId)
            ],

            'is_active' => ['boolean'],
        ];
    }
}
