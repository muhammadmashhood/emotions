<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the first super admin from SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD (local only).
 * In production use `php artisan app:create-super-admin`.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('emotion.super_admin.email');
        $password = config('emotion.super_admin.password');

        if (! is_string($email) || ! is_string($password) || $email === '' || $password === '') {
            $this->command->warn('SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD not set; skipping super admin seeding.');

            return;
        }

        if (app()->isProduction()) {
            $this->command->warn('Refusing to seed a super admin from env in production. Use app:create-super-admin.');

            return;
        }

        User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => 'Administrator', 'password' => $password, 'role' => Role::SuperAdmin, 'hospital_id' => null, 'is_active' => true],
        );
    }
}
