<?php

namespace App\Modules\User\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            /**
             * The user's email address used for login.
             * @example demo-company
             */
            'tenant' => [
                'required',
                'string',
            ],

            /**
             * The user's email address used for login.
             * @example admin@erp.com
             */
            'email' => [
                'required',
                'email',
            ],

            /**
             * The user's password used for login.
             * @example password123
             */
            'password' => [
                'required',
                'string',
            ],
        ];
    }
}