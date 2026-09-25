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

            'uuid' => $this->uuid,

            'voucher_no' => $this->voucher_no,

            'voucher_type'
                => $this->voucher_type?->value,

            'entry_date' => $this->entry_date,

            'description' => $this->description,

            'status'
                => $this->status?->value,

            'reference_type'
                => $this->reference_type,

            'reference_id'
                => $this->reference_id,

            'lines' => $this->whenLoaded(
                'lines',
                fn () => $this->lines->map(
                    fn ($line) => [

                        'uuid'
                            => $line->uuid,

                        'chart_of_account_uuid'
                            => $line->account?->uuid,

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