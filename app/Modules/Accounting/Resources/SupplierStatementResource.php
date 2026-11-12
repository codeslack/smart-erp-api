<?php

namespace App\Modules\Accounting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierStatementResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'date' =>
                $this['date'],

            'reference' =>
                $this['reference'],

            'type' =>
                $this['type'],

            'description' =>
                $this['description'],

            'debit' =>
                $this['debit'],

            'credit' =>
                $this['credit'],

            'currency_code' =>
                $this['currency_code'],

            'exchange_rate' =>
                $this['exchange_rate'],

            'document_amount' =>
                $this['document_amount'],

            'base_amount' =>
                $this['base_amount'],

            'balance' =>
                $this['balance'],
        ];
    }
}