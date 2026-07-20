<?php

namespace App\Modules\CustomerReceipt\Requests;

use Illuminate\Validation\Rule;
use App\Core\Validation\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\CustomerReceipt\Enums\CustomerReceiptType;

class StoreCustomerReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'customer_id' => [
                'required',
                TenantRule::exists(
                    'customers'
                ),
            ],

            'payment_account_id' => [
                'required',
                TenantRule::exists(
                    'chart_of_accounts'
                ),
            ],

            'receipt_date' => [
                'required',
                'date',
            ],

            'receipt_type' => [
                'required',
                Rule::enum(
                    CustomerReceiptType::class
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
                'nullable',
                'array',
            ],

            'allocations.*.sale_id' => [
                'required_with:allocations',
                TenantRule::exists(
                    'sales'
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
