<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hospital_id' => Hospital::factory(),
            'name' => fake()->unique()->city().' branch',
            'code' => strtoupper(fake()->lexify('???')),
            'is_active' => true,
        ];
    }
}
