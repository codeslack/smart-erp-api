<?php

namespace Database\Factories;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Tenant::class; // <-- Point this to your modular model

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name'      => $this->faker->company(),
            'slug'      => $this->faker->slug(),
            'domain'    => $this->faker->domainName(),
        ];
    }
}
