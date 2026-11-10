<?php

namespace App\Core\Validation;

use App\Core\Exceptions\BusinessException;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Builds validation rules constrained to the active tenant.
 *
 * A missing tenant context is an application configuration/security error;
 * it must never silently turn a tenant-scoped rule into a global rule.
 */
class TenantRule
{
    public static function unique(
        string $table,
        string $column
    ): Unique {
        return Rule::unique($table, $column)
            ->where('tenant_id', self::tenantId());
    }

    public static function exists(
        string $table,
        string $column = 'id'
    ): Exists {
        return Rule::exists($table, $column)
            ->where('tenant_id', self::tenantId());
    }

    public static function uniqueIgnore(
        string $table,
        string $column,
        int|string|null $ignoreId,
    ): Unique {
        $rule = Rule::unique($table, $column)
            ->where('tenant_id', self::tenantId());

        if ($ignoreId !== null && $ignoreId !== '') {
            // Only a server-resolved model key may be supplied here.
            $rule->ignore($ignoreId);
        }

        return $rule;
    }

    private static function tenantId(): int
    {
        $tenantId = tenant()?->getKey();

        if (! $tenantId) {
            throw new BusinessException(
                'An active tenant is required for tenant-scoped validation.',
                'TENANT_REQUIRED'
            );
        }

        return (int) $tenantId;
    }
}
