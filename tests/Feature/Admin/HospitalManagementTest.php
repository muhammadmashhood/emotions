<?php

use App\Livewire\Admin\Hospitals\Create;
use App\Livewire\Admin\Hospitals\Show;
use App\Models\Hospital;
use Livewire\Livewire;

test('super admin creates a hospital and it is audited', function () {
    $admin = superAdmin();

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('name', 'Ittefaq Hospital')
        ->set('require_review', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $hospital = Hospital::query()->where('name', 'Ittefaq Hospital')->firstOrFail();
    expect($hospital->slug)->toBe('ittefaq-hospital')
        ->and($hospital->require_review)->toBeTrue();

    $this->assertDatabaseHas('audit_logs', ['action' => 'hospital.created', 'subject_id' => $hospital->id, 'user_id' => $admin->id]);
});

test('hospital name is required', function () {
    Livewire::actingAs(superAdmin())
        ->test(Create::class)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('hospital settings can be updated', function () {
    $hospital = Hospital::factory()->create(['name' => 'Old']);

    Livewire::actingAs(superAdmin())
        ->test(Show::class, ['hospital' => $hospital])
        ->set('tab', 'settings')
        ->set('name', 'New name')
        ->set('is_active', false)
        ->call('saveSettings')
        ->assertHasNoErrors();

    $hospital->refresh();
    expect($hospital->name)->toBe('New name')->and($hospital->is_active)->toBeFalse();
    $this->assertDatabaseHas('audit_logs', ['action' => 'hospital.updated', 'subject_id' => $hospital->id]);
});

test('hospital admin cannot mount the admin hospital page', function () {
    $hospital = Hospital::factory()->create();

    Livewire::actingAs(hospitalAdmin($hospital))
        ->test(Show::class, ['hospital' => $hospital])
        ->assertForbidden();
});
