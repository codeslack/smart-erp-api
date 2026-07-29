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
            | Roles
            |--------------------------------------------------------------------------
            */

            'role_ids' => [
                'required',
                'array',
                'min:1',
                'distinct',
            ],

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