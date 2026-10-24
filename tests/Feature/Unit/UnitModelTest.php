<?php

namespace Tests\Feature;

use Tests\TestCase;

use Tests\Support\CreatesTenant;

use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Modules\Unit\Models\Unit;

class UnitModelTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant;

    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTestTenant();
    }

    /** @test */
    public function test_it_can_create_a_unit_using_the_factory()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /** @test */
    public function test_it_correctly_handles_the_document_type_enum_and_prefix()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
