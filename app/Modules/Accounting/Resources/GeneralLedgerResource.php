<?php

namespace App\Modules\Accounting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneralLedgerResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'account'
                => $this['account'],

            'opening_balance'
                => $this['opening_balance'],

            'transactions'
                => collect(
                    $this['transactions']
                )->map(
                    fn ($ledger) => [

                        'date'
                            => $ledger->entry_date,

                        'voucher_no'
                            => $ledger->voucher_no,

                        'voucher_type'
                            => $ledger->voucher_type,

                        'debit'
                            => $ledger->debit,

                        'credit'
                            => $ledger->credit,

                        'balance'
                            => $ledger->running_balance,

                        'description'
                            => $ledger->description,
                    ]
                ),

            'closing_balance'
                => $this['closing_balance'],
        ];
    }
}
