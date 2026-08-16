<?php

namespace App\Modules\User\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use App\Core\Requests\BaseRequest;

class StoreUserRequest extends BaseRequest
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
             * The user's full name.
             * @example John Doe
             */
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            /**
             * The user's email address, which must be unique across all users.
             * @example admin@erp.com
             */
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            /**
             * The user's phone number.
             * @example +1-555-123-4567
             */
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            /*
            |--------------------------------------------------------------------------
            | Authentication
            |--------------------------------------------------------------------------
            */

            /**
             * The user's password, which must be confirmed and meet minimum security requirements.
             * @example StrongPassword123!
             */
            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],

            /*
            |--------------------------------------------------------------------------
            | Roles
            |--------------------------------------------------------------------------
            */

            /**
             * An array of role IDs to assign to the user. Each role ID must exist in the roles table and belong to the current tenant.
             * @example [1, 2, 3]
             */
            'role_ids' => [
                'required',
                'array',
                'min:1',
                'distinct',
            ],

            /**
             * Each role ID in the role_ids array must be an integer and exist in the roles table for the current tenant.
             * @example 1
             */
            'role_ids.*' => [
                'integer',
                Rule::exists('roles', 'id')
                    ->where(
                        'tenant_id',
                        tenantId()
                    ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            /**
             * A boolean flag indicating whether the user is active or inactive.
             * @example true
             */
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [

            'role_ids'   => 'roles',
            'role_ids.*' => 'role',
        ];
    }
}