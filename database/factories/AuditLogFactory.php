<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'hospital_id' => null,
            'action' => 'test.action',
            'changes' => [],
            'ip' => '127.0.0.1',
            'created_at' => now(),
        ];
    }
}
