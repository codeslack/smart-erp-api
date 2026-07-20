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

            'balance' =>
                $this['balance'],
        ];
    }
}