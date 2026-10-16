<?php

namespace Database\Factories;

use App\Models\FormField;
use App\Models\FormVersion;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_version_id' => FormVersion::factory(),
            'typeform_field_id' => fake()->unique()->regexify('[A-Za-z0-9]{10}'),
            'typeform_ref' => fake()->uuid(),
            'title' => fake()->sentence(),
            'type' => 'opinion_scale',
            'section_id' => Section::factory(),
            'display_order' => 0,
            'is_scored' => true,
            'scale_min' => 0,
            'scale_max' => 10,
        ];
    }

    public function yesNo(): static
    {
        return $this->state(fn () => ['type' => 'yes_no', 'scale_min' => null, 'scale_max' => null, 'is_scored' => true]);
    }

    public function text(): static
    {
        return $this->state(fn () => ['type' => 'short_text', 'scale_min' => null, 'scale_max' => null, 'is_scored' => false]);
    }
}
