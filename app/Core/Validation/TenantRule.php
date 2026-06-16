<?php

/**
 * Implements or use this methods
 *
 * 'name' => [
 *     'required',
 *     TenantRule::unique('units', 'name'),
 * ];
 *
 * 'product_id' => [
 *    'required',
 *    TenantRule::exists('products'),
 *];
 */

namespace App\Core\Validation;

use Illuminate\Validation\Rule;

class TenantRule
{
    public static function unique(
        string $table,
        string $column
    )
    {
        return Rule::unique(
            $table,
            $column
        )->where(
            'tenant_id',
            tenant()->id
        );
    }

    public static function exists(
        string $table,
        string $column = 'id'
    )
    {
        $tenantId = tenant()->id;

        return Rule::exists(
            $table,
            $column
        )->where(
            'tenant_id',
            $tenantId
        );
    }
}