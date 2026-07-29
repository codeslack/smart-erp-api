<?php

namespace App\Modules\Tenant\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Tenant\Enums\BusinessTypeEnum;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | Company
            |--------------------------------------------------------------------------
            */

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:tenants,slug',
            ],

            'domain' => [
                'nullable',
                'string',
                'max:255',
                'unique:tenants,domain',
            ],

            'business_type' => [
                'required',
                Rule::enum(
                    BusinessTypeEnum::class
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Administrator
            |--------------------------------------------------------------------------
            */

            'admin_name' => [
                'required',
                'string',
                'max:255',
            ],

            'admin_email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'admin_password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->slug) {

            $this->merge([
                'slug' => str($this->slug)
                    ->slug()
                    ->toString(),
            ]);
        }
    }
}