<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'typeform_field_id' => fake()->regexify('[A-Za-z0-9]{10}'),
            'form_field_id' => null,
            'answer_type' => 'number',
            'value_text' => '7',
            'value_number' => 7,
            'points' => 7,
            'max_points' => 10,
        ];
    }
}
