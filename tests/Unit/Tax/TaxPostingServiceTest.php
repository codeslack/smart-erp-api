<?php

namespace Tests\Unit\Tax;

use App\Core\Exceptions\BusinessException;
use App\Modules\Tax\Domain\TaxCalculationResult;
use App\Modules\Tax\Domain\TaxComponentCalculationResult;
use App\Modules\Tax\Enums\TaxCalculationMethodEnum;
use App\Modules\Tax\Enums\TaxDirectionEnum;
use App\Modules\Tax\Models\TaxAccountingMapping;
use App\Modules\Tax\Repositories\Contracts\TaxAccountingMappingRepositoryInterface;
use App\Modules\Tax\Services\TaxPostingService;
use Mockery;
use Tests\TestCase;

class TaxPostingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_builds_posting_lines_for_multiple_tax_components(): void
    {
        $repository = Mockery::mock(
            TaxAccountingMappingRepositoryInterface::class,
        );

        $cgstMapping = $this->mapping(
            componentId: 1,
            accountId: 1210,
        );

        $sgstMapping = $this->mapping(
            componentId: 2,
            accountId: 1210,
        );

        $repository
            ->shouldReceive('findForPosting')
            ->once()
            ->with(
                1,
                TaxDirectionEnum::INPUT->value,
                '2026-06-01',
            )
            ->andReturn($cgstMapping);

        $repository
            ->shouldReceive('findForPosting')
            ->once()
            ->with(
                2,
                TaxDirectionEnum::INPUT->value,
                '2026-06-01',
            )
            ->andReturn($sgstMapping);

        $service = new TaxPostingService($repository);

        $calculation = new TaxCalculationResult(
            taxableAmount: 50000,
            totalTax: 9000,
            totalAmount: 59000,
            components: [
                new TaxComponentCalculationResult(
                    taxComponentId: 1,
                    componentType: 'CGST',
                    rate: 9,
                    taxableAmount: 50000,
                    taxAmount: 4500,
                    calculationMethod:
                        TaxCalculationMethodEnum::PERCENTAGE->value,
                ),
                new TaxComponentCalculationResult(
                    taxComponentId: 2,
                    componentType: 'SGST',
                    rate: 9,
                    taxableAmount: 50000,
                    taxAmount: 4500,
                    calculationMethod:
                        TaxCalculationMethodEnum::PERCENTAGE->value,
                ),
            ],
        );

        $lines = $service->buildLines(
            $calculation,
            TaxDirectionEnum::INPUT,
            '2026-06-01',
        );

        $this->assertCount(2, $lines);

        $this->assertSame(1, $lines[0]->taxComponentId);
        $this->assertSame(1210, $lines[0]->accountId);
        $this->assertSame(
            TaxDirectionEnum::INPUT->value,
            $lines[0]->direction,
        );
        $this->assertSame(4500.0, $lines[0]->amount);

        $this->assertSame(2, $lines[1]->taxComponentId);
        $this->assertSame(1210, $lines[1]->accountId);
        $this->assertSame(4500.0, $lines[1]->amount);
    }

    public function test_it_skips_zero_tax_components(): void
    {
        $repository = Mockery::mock(
            TaxAccountingMappingRepositoryInterface::class,
        );

        $repository
            ->shouldNotReceive('findForPosting');

        $service = new TaxPostingService($repository);

        $calculation = new TaxCalculationResult(
            taxableAmount: 3000,
            totalTax: 0,
            totalAmount: 3000,
            components: [
                new TaxComponentCalculationResult(
                    taxComponentId: 1,
                    componentType: 'CGST',
                    rate: 0,
                    taxableAmount: 3000,
                    taxAmount: 0,
                    calculationMethod:
                        TaxCalculationMethodEnum::PERCENTAGE->value,
                ),
            ],
        );

        $lines = $service->buildLines(
            $calculation,
            TaxDirectionEnum::INPUT,
            '2026-06-01',
        );

        $this->assertCount(0, $lines);
    }

    public function test_it_throws_when_accounting_mapping_is_missing(): void
    {
        $repository = Mockery::mock(
            TaxAccountingMappingRepositoryInterface::class,
        );

        $repository
            ->shouldReceive('findForPosting')
            ->once()
            ->andReturn(null);

        $service = new TaxPostingService($repository);

        $calculation = new TaxCalculationResult(
            taxableAmount: 50000,
            totalTax: 4500,
            totalAmount: 54500,
            components: [
                new TaxComponentCalculationResult(
                    taxComponentId: 1,
                    componentType: 'CGST',
                    rate: 9,
                    taxableAmount: 50000,
                    taxAmount: 4500,
                    calculationMethod:
                        TaxCalculationMethodEnum::PERCENTAGE->value,
                ),
            ],
        );

        $this->expectException(BusinessException::class);

        $service->buildLines(
            $calculation,
            TaxDirectionEnum::INPUT,
            '2026-06-01',
        );
    }

    public function test_it_preserves_output_direction(): void
    {
        $repository = Mockery::mock(
            TaxAccountingMappingRepositoryInterface::class,
        );

        $repository
            ->shouldReceive('findForPosting')
            ->once()
            ->with(
                1,
                TaxDirectionEnum::OUTPUT->value,
                '2026-06-01',
            )
            ->andReturn($this->mapping(
                componentId: 1,
                accountId: 2020,
            ));

        $service = new TaxPostingService($repository);

        $calculation = new TaxCalculationResult(
            taxableAmount: 10000,
            totalTax: 1800,
            totalAmount: 11800,
            components: [
                new TaxComponentCalculationResult(
                    taxComponentId: 1,
                    componentType: 'IGST',
                    rate: 18,
                    taxableAmount: 10000,
                    taxAmount: 1800,
                    calculationMethod:
                        TaxCalculationMethodEnum::PERCENTAGE->value,
                ),
            ],
        );

        $lines = $service->buildLines(
            $calculation,
            TaxDirectionEnum::OUTPUT,
            '2026-06-01',
        );

        $this->assertCount(1, $lines);
        $this->assertSame(
            TaxDirectionEnum::OUTPUT->value,
            $lines[0]->direction,
        );
        $this->assertSame(2020, $lines[0]->accountId);
        $this->assertSame(1800.0, $lines[0]->amount);
    }

    protected function mapping(
        int $componentId,
        int $accountId,
    ): TaxAccountingMapping {
        $mapping = new TaxAccountingMapping();

        $mapping->setAttribute(
            'tax_component_id',
            $componentId,
        );

        $mapping->setAttribute(
            'account_id',
            $accountId,
        );

        $mapping->setAttribute(
            'direction',
            TaxDirectionEnum::INPUT->value,
        );

        return $mapping;
    }
}
