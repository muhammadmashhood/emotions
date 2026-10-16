<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserInvitation>
 */
class UserInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'hospital_id' => Hospital::factory(),
            'role' => Role::HospitalViewer,
            'token_hash' => UserInvitation::hashToken(Str::random(64)),
            'invited_by' => null,
            'expires_at' => now()->addHours(72),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }
}
