<?php

namespace Tests\Feature\OpeningStock;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Exceptions\BusinessException;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Modules\OpeningStock\Services\OpeningStockService;

class OpeningStockCreateRollbackTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    public function test_opening_stock_creation_rolls_back_on_item_failure(): void
    {
        $tenant = $this->createTestTenant();

        $warehouse = $this->createWarehouse();

        $product = $this->createProduct();

        $service = app(
            OpeningStockService::class
        );

        try {
            $service->create([
                'warehouse_id' => $warehouse->id,
                'supplier_id' => null,
                'opening_date' => now()->toDateString(),
                'remarks' => 'Rollback test',

                'sources' => [
                    [
                        'supplier_id' => null,
                        'bill_no' => 'ROLLBACK-BILL',
                        'bill_date' => now()->toDateString(),
                        'remarks' => 'Rollback source',

                        'items' => [
                            [
                                'product_id' => $product->id,
                                'quantity' => 10,
                                'unit_cost' => 100,
                            ],
                            [
                                'product_id' => 999999999,
                                'quantity' => 5,
                                'unit_cost' => 200,
                            ],
                        ],
                    ],
                ],
            ]);

            $this->fail(
                'Expected item creation to fail.'
            );
        } catch (BusinessException) {
            // Expected database failure.
        }

        /*
         * Opening Stock itself must be rolled back.
         */
        $this->assertDatabaseMissing(
            'opening_stocks',
            [
                'tenant_id' => $tenant->id,
                'remarks' => 'Rollback test',
            ]
        );

        /*
         * Source created before the failed item
         * must also be rolled back.
         */
        $this->assertDatabaseMissing(
            'opening_stock_sources',
            [
                'tenant_id' => $tenant->id,
                'bill_no' => 'ROLLBACK-BILL',
            ]
        );

        /*
         * The valid first item must NOT remain
         * after the second item fails.
         */
        $this->assertDatabaseMissing(
            'opening_stock_items',
            [
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_cost' => 100,
            ]
        );
    }
}