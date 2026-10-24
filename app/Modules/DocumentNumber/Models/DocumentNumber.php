<?php

namespace App\Modules\DocumentNumber\Models;

use App\Core\Models\TenantModel;
use App\Modules\DocumentNumber\Enums\DocumentType;

class DocumentNumber extends TenantModel
{
    protected $table = 'document_numbers';

    protected $fillable = [

        'tenant_id',

        'document_type',

        'financial_year',

        'current_number',
    ];

    protected function casts(): array
    {
        return array_merge(
            parent::casts(),
            [
                'document_type' => DocumentType::class,
                'current_number' => 'integer',
            ]
        );
    }
}