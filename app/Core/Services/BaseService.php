<?php

namespace App\Core\Services;

abstract class BaseService
{
    /**
     * Standardize incoming payload data with architectural fallbacks.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    // protected function applyDefaults(array $data): array
    // {
    //     // Enforce Multi-Tenant Sovereignty Rule defensively
    //     if (tenant() && empty($data['tenant_id'])) {
    //         $data['tenant_id'] = tenant()->id;
    //     }

    //     // Apply fallback transactional and model states
    //     return array_merge([
    //         'is_active' => true,
    //         'attributes' => [],
    //     ], $data);
    // }
}