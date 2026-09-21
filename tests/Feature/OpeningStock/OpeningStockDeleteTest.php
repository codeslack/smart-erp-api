<?php

namespace Tests\Feature\OpeningStock;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Enums\DocumentStatusEnum;

use App\Modules\OpeningStock\Services\OpeningStockService;

class OpeningStockDeleteTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    public function test_opening_stock_can_be_deleted(): void
    {
        $tenant = $this->createTestTenant();

        $warehouse = $this->createWarehouse();

        $product = $this->createProduct();

        $service = app(
            OpeningStockService::class
        );

        $openingStock = $service->create([
            'warehouse_id' => $warehouse->id,
            'supplier_id' => null,
            'opening_date' => now()->toDateString(),
            'remarks' => 'Delete test',

            'sources' => [
                [
                    'supplier_id' => null,
                    'bill_no' => 'DELETE-BILL',
                    'bill_date' => now()->toDateString(),
                    'remarks' => 'Delete test source',

                    'items' => [
                        [
                            'product_id' => $product->id,
                            'quantity' => 10,
                            'unit_cost' => 100,
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertEquals(
            DocumentStatusEnum::DRAFT->value,
            $openingStock->status
        );

        $result = $service->delete(
            $openingStock
        );

        $this->assertTrue($result);

        $this->assertSoftDeleted(
            'opening_stocks',
            [
                'id' => $openingStock->id,
                'tenant_id' => $tenant->id,
            ]
        );

        /*
         * The source should also be deleted.
         */
        $this->assertSoftDeleted(
            'opening_stock_sources',
            [
                'tenant_id' => $tenant->id,
                'bill_no' => 'DELETE-BILL',
            ]
        );

        /*
         * The source item should be deleted.
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