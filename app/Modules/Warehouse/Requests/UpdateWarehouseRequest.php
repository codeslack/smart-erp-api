<?php

namespace App\Modules\Warehouse\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

class UpdateWarehouseRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            'name' => [
                'sometimes',
                'string',
                'max:255',

                TenantRule::uniqueIgnore(
                    'warehouses',
                    'name',
                    $this->route('warehouse')?->id
                ),
            ],

            'area_id' => [
                'nullable',

                TenantRule::exists(
                    'areas'
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Contact Information
            |--------------------------------------------------------------------------
            */

            'contact_person' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */

            'address' => [
                'nullable',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            'is_default' => [
                'sometimes',
                'boolean',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}