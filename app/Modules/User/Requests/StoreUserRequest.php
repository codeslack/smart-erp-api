<?php

namespace App\Modules\User\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
                'string'
            ],

            'email' => [
                'required',
                'email'
            ],

            'password' => [
                'required',
                'min:8'
            ],
        ];
    }
}
