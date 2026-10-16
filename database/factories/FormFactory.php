<?php

namespace Database\Factories;

use App\Domain\Forms\FormSettings;
use App\Enums\FormStatus;
use App\Enums\WebhookStatus;
use App\Models\Form;
use App\Models\Hospital;
use App\Models\TypeformConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'hospital_id' => Hospital::factory(),
            'typeform_connection_id' => TypeformConnection::factory(),
            'typeform_form_id' => fake()->unique()->regexify('[A-Za-z0-9]{8}'),
            'title' => fake()->sentence(3),
            'webhook_tag' => 'emotion-'.substr($uuid, 0, 8),
            'webhook_secret' => Str::random(48),
            'webhook_status' => WebhookStatus::Active,
            'settings' => new FormSettings,
            'status' => FormStatus::Active,
            'linked_by' => User::factory()->superAdmin(),
        ];
    }

    public function unlinked(): static
    {
        return $this->state(fn () => ['status' => FormStatus::Unlinked, 'webhook_status' => WebhookStatus::Unregistered]);
    }
}
