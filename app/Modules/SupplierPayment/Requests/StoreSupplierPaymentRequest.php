<?php

namespace App\Modules\SupplierPayment\Requests;

use App\Core\Validation\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

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

            'payment_method' => [
                'nullable',
                'string',
            ],

            'reference_no' => [
                'nullable',
                'string',
            ],

            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'allocations' => [
                'nullable',
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
                'min:0.01',
            ],
        ];
    }
}
