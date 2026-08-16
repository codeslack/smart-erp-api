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

            /**
             * The legal name of the corporate organization.
             * @example Demo Company
             */
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            /**
             * URL-friendly unique identifier derived from the company name.
             * @example demo-company
             */
            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:tenants,slug',
            ],

            /**
             * The system or custom domain configured for the tenant dashboard.
             * @example demo.local
             */
            'domain' => [
                'nullable',
                'string',
                'max:255',
                'unique:tenants,domain',
            ],

            /**
             * The industrial operational classification.
             * @example MEDICINE
             */
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

            /**
             * Full name of the default platform administrative user.
             * @example Administrator
             */
            'admin_name' => [
                'required',
                'string',
                'max:255',
            ],

            /**
             * Unique login email address for the initial root administrator.
             * @example admin@erp.com
             */
            'admin_email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            /**
             * Password for the initial root administrator account.
             * @example password123
             */
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