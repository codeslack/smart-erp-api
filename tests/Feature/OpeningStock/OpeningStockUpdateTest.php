<?php

namespace Tests\Feature\OpeningStock;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Enums\DocumentStatusEnum;

use App\Modules\OpeningStock\Services\OpeningStockService;

class OpeningStockUpdateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    public function test_opening_stock_updates_items_and_totals(): void
    {
        $tenant = $this->createTestTenant();

        $warehouse = $this->createWarehouse();

        $productA = $this->createProduct([
            'name' => 'Product A',
        ]);

        $productB = $this->createProduct([
            'name' => 'Product B',
            'purchase_price' => 50,
        ]);

        $service = app(
            OpeningStockService::class
        );

        /*
         * Create original opening stock.
         */
        $openingStock = $service->create([
            'warehouse_id' => $warehouse->id,
            'supplier_id' => null,
            'opening_date' => now()->toDateString(),
            'remarks' => 'Original opening stock',

            'sources' => [
                [
                    'supplier_id' => null,
                    'bill_no' => 'UPDATE-ORIGINAL',
                    'bill_date' => now()->toDateString(),
                    'remarks' => 'Original source',

                    'items' => [
                        [
                            'product_id' => $productA->id,
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

        /*
         * Update opening stock.
         */
        $updated = $service->update(
            $openingStock,
            [
                'warehouse_id' => $warehouse->id,
                'supplier_id' => null,
                'opening_date' => now()->toDateString(),
                'remarks' => 'Updated opening stock',

                'sources' => [
                    [
                        'supplier_id' => null,
                        'bill_no' => 'UPDATE-NEW',
                        'bill_date' => now()->toDateString(),
                        'remarks' => 'Updated source',

                        'items' => [
                            [
                                'product_id' => $productA->id,
                                'quantity' => 20,
                                'unit_cost' => 100,
                            ],
                            [
                                'product_id' => $productB->id,
                                'quantity' => 5,
                                'unit_cost' => 200,
                            ],
                        ],
                    ],
                ],
            ]
        );

        /*
         * Basic document assertions.
         */
        $this->assertEquals(
            'Updated opening stock',
            $updated->remarks
        );

        $this->assertEquals(
            25,
            (float) $updated->total_quantity
        );

        $this->assertEquals(
            3000,
            (float) $updated->total_amount
        );

        /*
         * Current relationship structure:
         *
         * OpeningStock
         *     └── sources
         *           └── items
         */
        $updated->load('sources.items');

        $this->assertCount(
            1,
            $updated->sources
        );

        $this->assertCount(
            2,
            $updated->sources->first()->items
        );

        /*
         * Verify updated Product A item.
         */
        $this->assertDatabaseHas(
            'opening_stock_items',
            [
                'opening_stock_source_id' =>
                    $updated->sources->first()->id,
                'product_id' => $productA->id,
                'quantity' => 20,
                'unit_cost' => 100,
                'total_cost' => 2000,
            ]
        );

        /*
         * Verify updated Product B item.
         */
        $this->assertDatabaseHas(
            'opening_stock_items',
            [
                'opening_stock_source_id' =>
                    $updated->sources->first()->id,
                'product_id' => $productB->id,
                'quantity' => 5,
                'unit_cost' => 200,
                'total_cost' => 1000,
            ]
        );

        /*
         * Old Product A quantity must no longer exist.
         */
        $this->assertDatabaseMissing(
            'opening_stock_items',
            [
                'opening_stock_source_id' =>
                    $updated->sources->first()->id,
                'product_id' => $productA->id,
                'quantity' => 10,
            ]
        );

        /*
         * Verify old source was replaced.
         */
        $this->assertSoftDeleted(
            'opening_stock_sources',
            [
                'tenant_id' => $tenant->id,
                'bill_no' => 'UPDATE-ORIGINAL',
            ]
        );

        $this->assertDatabaseHas(
            'opening_stock_sources',
            [
                'tenant_id' => $tenant->id,
                'bill_no' => 'UPDATE-NEW',
            ]
        );

        /*
         * Verify opening stock totals.
         */
        $this->assertDatabaseHas(
            'opening_stocks',
            [
                'id' => $openingStock->id,
                'tenant_id' => $tenant->id,
                'remarks' => 'Updated opening stock',
                'total_quantity' => 25,
                'total_amount' => 3000,
            ]
        );
    }
}