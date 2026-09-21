<?php

namespace Tests\Feature\OpeningStock;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesWarehouse;
use Tests\Support\CreatesProduct;

use App\Core\Exceptions\BusinessException;

use App\Modules\OpeningStock\Services\OpeningStockService;

class OpeningStockUpdateRollbackTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesWarehouse;
    use CreatesProduct;

    public function test_opening_stock_update_rolls_back_on_item_failure(): void
    {
        $tenant = $this->createTestTenant();

        $warehouse = $this->createWarehouse();

        $product = $this->createProduct();

        $service = app(
            OpeningStockService::class
        );

        $openingStock = $service->create([
            'warehouse_id' => $warehouse->id,
            'opening_date' => now()->toDateString(),
            'remarks' => 'Original opening stock',

            'sources' => [
                [
                    'supplier_id' => null,
                    'bill_no' => 'OPEN-ROLLBACK-001',
                    'bill_date' => now()->toDateString(),
                    'remarks' => 'Original source',

                    'items' => [
                        [
                            'product_id' => $product->id,
                            'product_variant_id' => null,
                            'quantity' => 10,
                            'unit_cost' => 100,
                            'remarks' => 'Original item',
                        ],
                    ],
                ],
            ],
        ]);

        try {
            $service->update(
                $openingStock,
                [
                    'warehouse_id' => $warehouse->id,
                    'opening_date' => now()->toDateString(),
                    'remarks' => 'Failed update',

                    'sources' => [
                        [
                            'supplier_id' => null,
                            'bill_no' => 'OPEN-ROLLBACK-002',
                            'bill_date' => now()->toDateString(),
                            'remarks' => 'Failed source',

                            'items' => [
                                [
                                    'product_id' => $product->id,
                                    'product_variant_id' => null,
                                    'quantity' => 20,
                                    'unit_cost' => 100,
                                    'remarks' => 'Updated item',
                                ],
                                [
                                    'product_id' => 999999999,
                                    'product_variant_id' => null,
                                    'quantity' => 5,
                                    'unit_cost' => 200,
                                    'remarks' => 'Invalid item',
                                ],
                            ],
                        ],
                    ],
                ]
            );

            $this->fail(
                'Expected item creation to fail.'
            );
        } catch (BusinessException $e) {
            $this->assertEquals(
                'Product not found.',
                $e->getMessage()
            );
        }

        $openingStock->refresh();

        $this->assertEquals(
            'Original opening stock',
            $openingStock->remarks
        );

        $this->assertEquals(
            10,
            (float) $openingStock->total_quantity
        );

        $this->assertEquals(
            1000,
            (float) $openingStock->total_amount
        );

        $openingStock->load([
            'sources.items',
        ]);

        $this->assertCount(
            1,
            $openingStock->sources
        );

        $source = $openingStock->sources->first();

        $this->assertEquals(
            'OPEN-ROLLBACK-001',
            $source->bill_no
        );

        $this->assertCount(
            1,
            $source->items
        );

        $item = $source->items->first();

        $this->assertEquals(
            $product->id,
            $item->product_id
        );

        $this->assertEquals(
            10,
            (float) $item->quantity
        );

        $this->assertEquals(
            100,
            (float) $item->unit_cost
        );

        $this->assertEquals(
            1000,
            (float) $item->total_cost
        );

        $this->assertDatabaseCount(
            'opening_stock_sources',
            1
        );

        $this->assertDatabaseCount(
            'opening_stock_items',
            1
        );

        $this->assertDatabaseHas(
            'opening_stock_items',
            [
                'tenant_id' => $tenant->id,
                'opening_stock_source_id' => $source->id,
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_cost' => 100,
                'total_cost' => 1000,
            ]
        );
    }
}
