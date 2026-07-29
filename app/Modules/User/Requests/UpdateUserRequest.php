<?php

namespace App\Modules\User\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use App\Core\Requests\BaseRequest;

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
            | Roles
            |--------------------------------------------------------------------------
            */

            'role_ids' => [
                'sometimes',
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