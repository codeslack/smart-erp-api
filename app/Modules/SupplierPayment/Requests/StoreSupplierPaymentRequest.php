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
                'required',
                'array',
                'min:1',
            ],

            'allocations.*.purchase_id' => [
                'required',
                TenantRule::exists(
                    'purchases'
                ),
            ],

            'allocations.*.allocated_amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
        ];
    }
}
