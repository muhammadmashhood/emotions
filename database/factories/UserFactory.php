<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::HospitalViewer,
            'hospital_id' => Hospital::factory(),
            'is_active' => true,
            'remember_token' => null,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['role' => Role::SuperAdmin, 'hospital_id' => null]);
    }

    public function hospitalAdmin(?Hospital $hospital = null): static
    {
        return $this->state(fn () => array_filter([
            'role' => Role::HospitalAdmin,
            'hospital_id' => $hospital?->id,
        ]));
    }

    public function viewer(?Hospital $hospital = null): static
    {
        return $this->state(fn () => array_filter([
            'role' => Role::HospitalViewer,
            'hospital_id' => $hospital?->id,
        ]));
    }

    public function forHospital(Hospital $hospital): static
    {
        return $this->state(fn () => ['hospital_id' => $hospital->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
