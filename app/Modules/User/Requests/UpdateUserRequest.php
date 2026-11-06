<?php

namespace App\Modules\User\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

class UpdateUserRequest extends BaseRequest
{
    public function rules(): array
    {
        $user = $this->route('user');

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
            ],

            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($user?->id),
            ],

            'phone' => [
                'sometimes',
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
                'nullable',
                'confirmed',
                Password::min(8),
            ],

            /*
            |--------------------------------------------------------------------------
            | Tenant Roles
            |--------------------------------------------------------------------------
            */

            'role_uuids' => [
                'sometimes',
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
