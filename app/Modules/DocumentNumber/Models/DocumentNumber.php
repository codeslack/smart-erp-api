<?php

namespace App\Modules\DocumentNumber\Models;

use App\Core\Models\TenantModel;

class DocumentNumber extends TenantModel
{
    protected $table = 'document_numbers';

    protected $fillable = [

        'tenant_id',

        'document_type',

        'financial_year',

        'current_number',
    ];
}