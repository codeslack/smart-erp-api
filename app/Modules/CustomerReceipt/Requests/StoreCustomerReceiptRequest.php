<?php

namespace App\Modules\CustomerReceipt\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerReceiptRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return []; }
}
