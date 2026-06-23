<?php

namespace App\Modules\CustomerReceipt\Requests;

use App\Core\Validation\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerReceiptRequest extends FormRequest
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

            'receipt_date' => [
                'required',
                'date',
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
                'required',
                'array',
                'min:1',
            ],

            'allocations.*.sale_id' => [
                'required',
                TenantRule::exists(
                    'sales'
                ),
            ],

            'allocations.*.allocated_amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }
}
