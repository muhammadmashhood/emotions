<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hospital>
 */
class HospitalFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Hospital';

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
            'require_review' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function requiresReview(): static
    {
        return $this->state(fn () => ['require_review' => true]);
    }
}
