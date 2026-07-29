<?php

namespace App\Modules\Settings\Requests;

use App\Modules\Settings\Enums\InventoryCostingMethodEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match (
            $this->route('group')
        ) {

            'company' => [
                'currency' => ['required', 'string'],
                'timezone' => ['required', 'string'],
                'country'  => ['nullable', 'string'],
                'state'    => ['nullable', 'string'],
                'city'     => ['nullable', 'string'],
            ],

            'inventory' => [
                'allow_negative_stock' => ['required', 'boolean'],
                'costing_method' => [
                    'required',
                    Rule::enum(
                        InventoryCostingMethodEnum::class
                    ),
                ],
            ],

            'sales' => [
                'allow_below_cost_sale' => [
                    'required',
                    'boolean',
                ],
                'discount_before_tax' => [
                    'required',
                    'boolean',
                ],
            ],

            'purchase' => [
                'require_grn' => [
                    'required',
                    'boolean',
                ],
            ],

            'accounting' => [
                'auto_post_purchase' => [
                    'required',
                    'boolean',
                ],
                'auto_post_sales' => [
                    'required',
                    'boolean',
                ],
            ],

            'tax' => [
                'gst_enabled' => [
                    'required',
                    'boolean',
                ],
                'default_tax_rate' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:100',
                ],
            ],

            default => [],
        };
    }
}