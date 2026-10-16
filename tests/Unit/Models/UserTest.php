<?php

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a super admin cannot belong to a hospital', function () {
    $hospital = Hospital::factory()->create();

    User::factory()->create(['role' => Role::SuperAdmin, 'hospital_id' => $hospital->id]);
})->throws(InvalidArgumentException::class);

test('a hospital user must belong to a hospital', function () {
    User::factory()->create(['role' => Role::HospitalAdmin, 'hospital_id' => null]);
})->throws(InvalidArgumentException::class);

test('canAccess reflects user and hospital activity', function () {
    $hospital = Hospital::factory()->create();
    $user = User::factory()->viewer($hospital)->create();
    expect($user->canAccess())->toBeTrue();

    $user->is_active = false;
    expect($user->canAccess())->toBeFalse();

    $user->is_active = true;
    $hospital->update(['is_active' => false]);
    expect($user->fresh()->canAccess())->toBeFalse();

    expect(User::factory()->superAdmin()->create()->canAccess())->toBeTrue();
});

test('hospital slugs are generated uniquely', function () {
    $a = Hospital::query()->create(['name' => 'CMH']);
    $b = Hospital::query()->create(['name' => 'CMH']);

    expect($a->slug)->toBe('cmh')
        ->and($b->slug)->toBe('cmh-2')
        ->and($a->uuid)->not->toBeEmpty();
});
