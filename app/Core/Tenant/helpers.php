<?php

use App\Core\Tenant\TenantManager;
use App\Modules\DocumentNumber\Services\DocumentNumberService;

if (! function_exists('tenant')) {

    /**
     * Get the current tenant instance.
     *
     * Returns the tenant resolved from the TenantManager
     * for the current request context.
     *
     * Example:
     * tenant()->id;
     * tenant()->name;
     *
     * @return mixed
     */
    function tenant()
    {
        return app(TenantManager::class)
            ->getTenant();
    }
}

if (! function_exists('tenantId')) {

    /**
     * Get current tenant ID.
     *
     * @throws \RuntimeException
     * @return int
     */
    function tenantId(): int
    {
        $tenant = tenant();

        if (! $tenant) {
            throw new RuntimeException(
                'No active tenant found.'
            );
        }

        return (int) $tenant->id;
    }
}

if (! function_exists('nextDocumentNumber')) {

    /**
     * Generate the next tenant-aware document number.
     *
     * The sequence is maintained separately for:
     * - Tenant
     * - Document Type
     * - Financial Year
     *
     * Example:
     * nextDocumentNumber('customer_receipt', 'REC');
     * // REC-2026-000001
     *
     * nextDocumentNumber('supplier_payment', 'PAY');
     * // PAY-2026-000001
     *
     * @param string $documentType e.g., 'customer_receipt'
     * @param string $prefix       e.g., 'REC'
     * @return string              e.g., 'REC-2026-000001'
     */
    function nextDocumentNumber(
        string $documentType,
        string $prefix
    ): string {

        return app(
            DocumentNumberService::class
        )->next(
            $documentType,
            $prefix
        );
    }
}