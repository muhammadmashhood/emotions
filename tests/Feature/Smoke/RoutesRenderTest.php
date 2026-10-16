<?php

use App\Models\Hospital;

/**
 * Every screen renders for the right role with seeded data (no 500s).
 * Extended in each milestone as routes are added.
 */
test('routes render for their role', function (string $role, string $routeName, array $params = []) {
    $hospital = Hospital::factory()->create(['name' => 'Smoke Hospital']);
    $user = match ($role) {
        'super_admin' => superAdmin(),
        'hospital_admin' => hospitalAdmin($hospital),
        default => viewer($hospital),
    };

    $resolved = array_map(fn ($p) => $p === ':hospital' ? $hospital : $p, $params);

    $this->actingAs($user)->get(route($routeName, $resolved))->assertOk();
})->with([
    ['super_admin', 'admin.overview'],
    ['super_admin', 'admin.hospitals.index'],
    ['super_admin', 'admin.hospitals.create'],
    ['super_admin', 'admin.hospitals.show', [':hospital']],
    ['super_admin', 'admin.users.index'],
    ['super_admin', 'profile'],
    ['hospital_admin', 'dashboard'],
    ['hospital_admin', 'team'],
    ['hospital_admin', 'profile'],
    ['hospital_viewer', 'dashboard'],
    ['hospital_viewer', 'profile'],
]);
