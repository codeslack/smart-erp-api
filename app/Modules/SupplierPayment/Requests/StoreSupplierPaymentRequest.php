<?php

namespace App\Modules\SupplierPayment\Requests;

use Illuminate\Validation\Rule;
use App\Core\Validation\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\SupplierPayment\Enums\SupplierPaymentType;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'supplier_id' => [
                'required',
                TenantRule::exists(
                    'suppliers'
                ),
            ],

            'payment_account_id' => [
                'required',
                TenantRule::exists(
                    'chart_of_accounts'
                ),
            ],

            'payment_date' => [
                'required',
                'date',
            ],

            'payment_type' => [
                'required',
                Rule::enum(
                    SupplierPaymentType::class
                ),
            ],

            'payment_method' => [
                'nullable',
                'string',
                'max:100',
            ],

            'reference_no' => [
                'nullable',
                'string',
                'max:255',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'allocations' => [
                'sometimes',
                'array',
            ],

            'allocations.*.purchase_id' => [
                'required_with:allocations',
                TenantRule::exists(
                    'purchases'
                ),
            ],

            'allocations.*.allocated_amount' => [
                'required_with:allocations',
                'numeric',
                'gt:0',
            ],
        ];
    }
}
