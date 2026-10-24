<?php

namespace App\Modules\GoodsReceiptNote\Requests;

use App\Core\Requests\BaseRequest;
use App\Core\Validation\TenantRule;

class StoreGoodsReceiptNoteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'grn_date' => [
                'required',
                'date',
            ],

            'purchase_order_id' => [
                'required',
                'integer',
                TenantRule::exists('purchase_orders'),
            ],

            'warehouse_id' => [
                'nullable',
                'integer',
                TenantRule::exists('warehouses'),
            ],

            'delivery_challan_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'delivery_challan_date' => [
                'nullable',
                'date',
            ],

            'vehicle_number' => [
                'nullable',
                'string',
                'max:50',
            ],

            'transporter_name' => [
                'nullable',
                'string',
                'max:150',
            ],

            'remarks' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.purchase_order_item_id' => [
                'required',
                'integer',
                TenantRule::exists('purchase_order_items'),
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                TenantRule::exists('products'),
            ],

            'items.*.product_variant_id' => [
                'nullable',
                'integer',
                TenantRule::exists('product_variants'),
            ],

            'items.*.unit_id' => [
                'required',
                'integer',
                TenantRule::exists('units'),
            ],

            'items.*.quantity_received' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'items.*.unit_cost' => [
                'required',
                'numeric',
                'gte:0',
            ],

            'items.*.tax_group_id' => [
                'nullable',
                'integer',
                TenantRule::exists('tax_groups'),
            ],

            'items.*.tax_rate' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'items.*.tax_amount' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'items.*.remarks' => [
                'nullable',
                'string',
                'max:500',
            ],

            'items.*.batches' => [
                'nullable',
                'array',
            ],

            'items.*.batches.*.batch_no' => [
                'required_with:items.*.batches',
                'string',
                'max:100',
            ],

            'items.*.batches.*.quantity' => [
                'required_with:items.*.batches',
                'numeric',
                'gt:0',
            ],

            'items.*.batches.*.expiry_date' => [
                'nullable',
                'date',
            ],

            'items.*.serials' => [
                'nullable',
                'array',
            ],

            'items.*.serials.*.serial_number' => [
                'required_with:items.*.serials',
                'string',
                'max:150',
            ],
        ];
    }
}