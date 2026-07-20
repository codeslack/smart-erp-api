<?php

namespace App\Modules\DocumentNumber\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\DocumentNumber\Models\DocumentNumber;

use App\Modules\Accounting\Services\FinancialYearService;

class DocumentNumberService
{
    public function __construct(
        protected FinancialYearService $financialYearService
    ) {}

    public function next(
        string $documentType,
        string $prefix
    ): string {

        return DB::transaction(
            function () use (
                $documentType,
                $prefix
            ) {

                $financialYear =
                    $this->financialYearService
                        ->current();

                $sequence =
                    DocumentNumber::query()

                        ->lockForUpdate()

                        ->firstOrCreate(
                            [

                                'tenant_id' =>
                                    tenantId(),

                                'document_type' =>
                                    $documentType,

                                'financial_year' =>
                                    $financialYear,
                            ],
                            [

                                'current_number' =>
                                    0,
                            ]
                        );

                $sequence->increment(
                    'current_number'
                );

                $sequence->refresh();

                return sprintf(

                    '%s-%s-%06d',

                    $prefix,

                    $financialYear,

                    $sequence->current_number
                );
            }
        );
    }
}