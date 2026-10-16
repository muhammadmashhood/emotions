<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development. Extended in later milestones with forms and evaluations.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        foreach (['Ittefaq Hospital' => 'ittefaq', 'CMH' => 'cmh'] as $name => $slug) {
            $hospital = Hospital::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);

            User::query()->firstOrCreate(
                ['email' => "admin@{$slug}.example.com"],
                ['name' => "{$name} admin", 'password' => 'password', 'role' => Role::HospitalAdmin, 'hospital_id' => $hospital->id],
            );
            User::query()->firstOrCreate(
                ['email' => "viewer@{$slug}.example.com"],
                ['name' => "{$name} viewer", 'password' => 'password', 'role' => Role::HospitalViewer, 'hospital_id' => $hospital->id],
            );
        }
    }
}
