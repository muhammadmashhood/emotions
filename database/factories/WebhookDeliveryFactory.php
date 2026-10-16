<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'received_at' => now(),
            'signature_valid' => true,
            'event_id' => fake()->uuid(),
            'event_type' => 'form_response',
            'response_token' => fake()->regexify('[a-z0-9]{20}'),
            'payload' => '{}',
        ];
    }
}
