<?php

namespace App\Modules\Warehouse\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

class StoreWarehouseRequest extends BaseRequest
{
    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Basic Information
            |--------------------------------------------------------------------------
            */

            /**
             * The warehouse name, which must be unique within the tenant's context.
             * @example Main Warehouse
             */
            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique(
                    'warehouses',
                    'name'
                ),
            ],

            /**
             * Area ID associated with the warehouse, which must exist in the areas table for the current tenant.
             * @example 1
             */
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

            /**
             * The contact person's name for the warehouse.
             * @example John Doe
             */
            'contact_person' => [
                'nullable',
                'string',
                'max:255',
            ],

            /**
             * The contact phone number for the warehouse.
             * @example +1-555-123-4567
             */
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            /**
             * The contact email address for the warehouse.
             * @example wh.main@erp.com
             */
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

            /**
             * The physical address of the warehouse.
             * @example 123 Main St, West Bengal, India
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

            /**
             * A boolean flag indicating whether the warehouse is the default warehouse for the tenant.
             * @example true
             */
            'is_default' => [
                'sometimes',
                'boolean',
            ],

            /**
             * A boolean flag indicating whether the warehouse is active or inactive.
             * @example true
             */
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}