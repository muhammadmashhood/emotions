<?php

namespace Database\Factories;

use App\Models\Section;
use App\Models\Submission;
use App\Models\SubmissionSectionScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmissionSectionScore>
 */
class SubmissionSectionScoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'section_id' => Section::factory(),
            'section_name' => fake()->words(2, true),
            'display_order' => 0,
            'points' => 8,
            'max_points' => 10,
            'pct' => 80,
            'weight' => 1,
        ];
    }
}
