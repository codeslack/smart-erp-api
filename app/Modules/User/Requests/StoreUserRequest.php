<?php

namespace App\Modules\User\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

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

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],

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

            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],

            /*
            |--------------------------------------------------------------------------
            | Tenant Roles
            |--------------------------------------------------------------------------
            */

            'role_uuids' => [
                'required',
                'array',
                'min:1',
            ],

            'role_uuids.*' => [
                'required',
                'string',
                'uuid',
                'distinct:strict',

                TenantRule::exists(
                    'roles',
                    'uuid'
                )->where('guard_name', 'sanctum'),
            ],

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
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
            'role_uuids' => 'roles',
            'role_uuids.*' => 'role',
        ];
    }
}
