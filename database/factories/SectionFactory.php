<?php

namespace Database\Factories;

use App\Models\FormVersion;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_version_id' => FormVersion::factory(),
            'name' => fake()->words(2, true),
            'source_ref' => fake()->uuid(),
            'display_order' => 0,
            'weight' => 1.0,
        ];
    }
}
