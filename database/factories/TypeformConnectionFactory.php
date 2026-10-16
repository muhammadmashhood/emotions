<?php

namespace Database\Factories;

use App\Models\TypeformConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TypeformConnection>
 */
class TypeformConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => 'Main Typeform account',
            'base_url' => 'https://api.typeform.com',
            'access_token' => 'tfp_'.fake()->regexify('[A-Za-z0-9]{40}'),
            'account_alias' => fake()->userName(),
            'last_verified_at' => now(),
        ];
    }
}
