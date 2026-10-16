<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormVersion>
 */
class FormVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'version_number' => 1,
            'definition' => ['id' => 'abc', 'title' => 'Form', 'fields' => []],
            'definition_hash' => hash('sha256', (string) fake()->unique()->randomNumber()),
            'needs_review' => false,
        ];
    }
}
