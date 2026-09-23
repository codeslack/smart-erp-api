<?php

namespace Tests\Feature\Supplier;

use Tests\TestCase;

use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\Support\CreatesSupplier;

use App\Modules\Tenant\Models\Tenant;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Core\Enums\OpeningBalanceTypeEnum;
use App\Modules\Accounting\Services\AccountingSetupService;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\SupplierOpeningBill\Models\SupplierOpeningBill;

class SupplierOpeningBalanceApiTest extends TestCase
{
    protected Tenant $tenant;
    
    use RefreshDatabase;
    use CreatesTenant;
    use CreatesUser;
    use CreatesSupplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
        $this->actingAs($this->createTestUser(), 'sanctum');

        app(AccountingSetupService::class)
            ->setup($this->tenant);
    }

    public function test_create_supplier_with_opening_bill(): void
    {
        $payload = [
            'name' => 'Opening Supplier',
            'code' => 'SUP-OPEN-001',
            'opening_bills' => [
                [
                    'bill_no' => 'OB-SUP-001',
                    'bill_date' => '2026-01-01',
                    'due_date' => '2026-01-31',
                    'amount' => 5000,
                    'balance_amount' => 5000,
                    'balance_type' => OpeningBalanceTypeEnum::CREDIT->value,
                    'notes' => 'Opening supplier bill',
                ],
            ],
        ];

        $response = $this->postJson(
            '/api/suppliers',
            $payload
        );

        $response->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Opening Supplier'
            )
            ->assertJsonCount(
                1,
                'data.opening_bills'
            )
            ->assertJsonPath(
                'data.opening_bills.0.bill_no',
                'OB-SUP-001'
            )
            ->assertJsonPath(
                'data.opening_bills.0.amount',
                '5000.0000'
            )
            ->assertJsonPath(
                'data.opening_bills.0.balance_amount',
                '5000.0000'
            )
            ->assertJsonPath(
                'data.opening_bills.0.balance_type',
                OpeningBalanceTypeEnum::CREDIT->value
            );

        $this->assertDatabaseHas(
            'suppliers',
            [
                'name' => 'Opening Supplier',
            ]
        );

        $this->assertDatabaseHas(
            'supplier_opening_bills',
            [
                'bill_no' => 'OB-SUP-001',
                'amount' => 5000,
                'balance_amount' => 5000,
                'balance_type' => OpeningBalanceTypeEnum::CREDIT->value,
            ]
        );
    }

    public function test_update_supplier_adds_opening_bill(): void
    {
        $supplier = $this->createSupplier([
            'name' => 'Supplier Add Bill',
            'code' => 'SUP-ADD-001',
        ]);

        $response = $this->putJson(
            "/api/suppliers/{$supplier->uuid}",
            [
                'name' => 'Supplier Add Bill',
                'opening_bills' => [
                    [
                        'bill_no' => 'OB-SUP-002',
                        'bill_date' => '2026-01-05',
                        'due_date' => '2026-02-05',
                        'amount' => 7500,
                        'balance_amount' => 6000,
                        'balance_type' => OpeningBalanceTypeEnum::CREDIT->value,
                    ],
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonCount(
                1,
                'data.opening_bills'
            )
            ->assertJsonPath(
                'data.opening_bills.0.bill_no',
                'OB-SUP-002'
            );

        $this->assertDatabaseHas(
            'supplier_opening_bills',
            [
                'supplier_id' => $supplier->id,
                'bill_no' => 'OB-SUP-002',
                'amount' => 7500,
                'balance_amount' => 6000,
            ]
        );
    }

    public function test_update_supplier_updates_existing_opening_bill(): void
    {
        $supplier = $this->createSupplier([
            'name' => 'Supplier Update Bill',
            'code' => 'SUP-UPD-001',
        ]);

        $bill = SupplierOpeningBill::create([
            'supplier_id' => $supplier->id,
            'bill_no' => 'OB-SUP-003',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 4000,
            'balance_amount' => 4000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
        ]);

        $response = $this->putJson(
            "/api/suppliers/{$supplier->uuid}",
            [
                'opening_bills' => [
                    [
                        'uuid' => $bill->uuid,
                        'bill_no' => 'OB-SUP-003-UPDATED',
                        'bill_date' => '2026-01-02',
                        'due_date' => '2026-02-02',
                        'amount' => 5000,
                        'balance_amount' => 3500,
                        'balance_type' => OpeningBalanceTypeEnum::CREDIT->value,
                    ],
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonPath(
                'data.opening_bills.0.bill_no',
                'OB-SUP-003-UPDATED'
            )
            ->assertJsonPath(
                'data.opening_bills.0.amount',
                '5000.0000'
            )
            ->assertJsonPath(
                'data.opening_bills.0.balance_amount',
                '3500.0000'
            );

        $this->assertDatabaseHas(
            'supplier_opening_bills',
            [
                'id' => $bill->id,
                'bill_no' => 'OB-SUP-003-UPDATED',
                'amount' => 5000,
                'balance_amount' => 3500,
            ]
        );
    }

    public function test_update_supplier_deletes_opening_bill(): void
    {
        $supplier = $this->createSupplier([
            'name' => 'Supplier Delete Bill',
            'code' => 'SUP-DEL-001',
        ]);

        $bill = SupplierOpeningBill::create([
            'supplier_id' => $supplier->id,
            'bill_no' => 'OB-SUP-004',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 3000,
            'balance_amount' => 3000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
        ]);

        $response = $this->putJson(
            "/api/suppliers/{$supplier->uuid}",
            [
                'opening_bills' => [
                    [
                        'uuid' => $bill->uuid,
                        'delete' => true,
                    ],
                ],
            ]
        );

        $response->assertOk()
            ->assertJsonCount(
                0,
                'data.opening_bills'
            );

        $this->assertSoftDeleted(
            'supplier_opening_bills',
            [
                'id' => $bill->id,
            ]
        );
    }

    public function test_update_supplier_without_opening_bills_leaves_existing_bill_unchanged(): void
    {
        $supplier = $this->createSupplier([
            'name' => 'Supplier Preserve Bill',
            'code' => 'SUP-PRESERVE-001',
        ]);

        $bill = SupplierOpeningBill::create([
            'supplier_id' => $supplier->id,
            'bill_no' => 'OB-SUP-005',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 6000,
            'balance_amount' => 4500,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
        ]);

        $response = $this->putJson(
            "/api/suppliers/{$supplier->uuid}",
            [
                'name' => 'Supplier Preserve Bill Updated',
            ]
        );

        $response->assertOk();

        $this->assertDatabaseHas(
            'supplier_opening_bills',
            [
                'id' => $bill->id,
                'bill_no' => 'OB-SUP-005',
                'amount' => 6000,
                'balance_amount' => 4500,
                'deleted_at' => null,
            ]
        );
    }

    public function test_update_supplier_rejects_opening_bill_from_another_supplier(): void
    {
        $supplier = $this->createSupplier([
            'name' => 'Supplier One',
            'code' => 'SUP-CROSS-001',
        ]);

        $anotherSupplier = $this->createSupplier([
            'name' => 'Supplier Two',
            'code' => 'SUP-CROSS-002',
        ]);

        $bill = SupplierOpeningBill::create([
            'supplier_id' => $anotherSupplier->id,
            'bill_no' => 'OB-SUP-CROSS-001',
            'bill_date' => '2026-01-01',
            'due_date' => '2026-01-31',
            'amount' => 2000,
            'balance_amount' => 2000,
            'balance_type' => OpeningBalanceTypeEnum::CREDIT,
        ]);

        $response = $this->putJson(
            "/api/suppliers/{$supplier->uuid}",
            [
                'opening_bills' => [
                    [
                        'uuid' => $bill->uuid,
                        'bill_no' => 'OB-SUP-CROSS-001',
                        'bill_date' => '2026-01-01',
                        'due_date' => '2026-01-31',
                        'amount' => 2000,
                        'balance_amount' => 2000,
                        'balance_type' => OpeningBalanceTypeEnum::CREDIT->value,
                    ],
                ],
            ]
        );

        $response->assertStatus(422);

        $this->assertDatabaseHas(
            'supplier_opening_bills',
            [
                'id' => $bill->id,
                'supplier_id' => $anotherSupplier->id,
            ]
        );
    }
}