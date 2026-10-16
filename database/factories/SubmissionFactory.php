<?php

namespace Database\Factories;

use App\Enums\Rag;
use App\Enums\SubmissionSource;
use App\Enums\SubmissionStatus;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        $pct = fake()->randomFloat(2, 30, 100);

        return [
            'uuid' => (string) Str::uuid(),
            'form_id' => Form::factory(),
            'form_version_id' => fn (array $attrs) => FormVersion::factory()->for(Form::find($attrs['form_id'])),
            'hospital_id' => fn (array $attrs) => Form::find($attrs['form_id'])?->hospital_id,
            'response_token' => fake()->unique()->regexify('[a-z0-9]{24}'),
            'submitted_at' => now()->subDays(fake()->numberBetween(0, 80)),
            'landed_at' => now()->subDays(fake()->numberBetween(0, 80)),
            'is_partial' => false,
            'status' => SubmissionStatus::Approved,
            'source' => SubmissionSource::Webhook,
            'raw' => ['answers' => [], 'hidden' => [], 'variables' => [], 'calculated' => [], 'event_type' => 'form_response'],
            'info' => [],
            'points' => $pct,
            'max_points' => 100,
            'overall_pct' => $pct,
            'rag' => Rag::fromPct($pct, 80, 60),
            'scored_at' => now(),
        ];
    }

    public function partial(): static
    {
        return $this->state(fn () => ['is_partial' => true]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn () => ['status' => SubmissionStatus::PendingReview]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => SubmissionStatus::Rejected]);
    }

    public function withScore(float $pct): static
    {
        return $this->state(fn () => ['overall_pct' => $pct, 'points' => $pct, 'max_points' => 100, 'rag' => Rag::fromPct($pct, 80, 60)]);
    }
}
