<?php

namespace App\Modules\DocumentNumber\Models;

use App\Core\Tenant\Models\TenantModel;

class DocumentNumber extends TenantModel
{
    protected $fillable = [

        'tenant_id',

        'document_type',

        'financial_year',

        'current_number',
    ];
}