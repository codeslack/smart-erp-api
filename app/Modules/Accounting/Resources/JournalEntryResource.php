<?php

namespace App\Modules\Accounting\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {

        return [

            'id' => $this->id,

            'voucher_no' => $this->voucher_no,

            'voucher_type' => $this->voucher_type,

            'entry_date' => $this->entry_date,

            'description' => $this->description,

            'status' => $this->status,

            'reference_type'
                => $this->reference_type,

            'reference_id'
                => $this->reference_id,

            'lines' => $this->whenLoaded(
                'lines',
                fn () => $this->lines->map(
                    fn ($line) => [

                        'id'
                            => $line->id,

                        'chart_of_account_id'
                            => $line->chart_of_account_id,

                        'account_name'
                            => $line->account?->account_name,

                        'debit'
                            => $line->debit,

                        'credit'
                            => $line->credit,

                        'description'
                            => $line->description,
                    ]
                )
            ),
        ];
    }
}
