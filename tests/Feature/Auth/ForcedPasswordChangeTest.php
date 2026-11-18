<?php

use App\Livewire\Auth\SetPassword;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('flagged user is redirected from every page to set-password', function () {
    $user = hospitalAdmin();
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.force'));
    $this->actingAs($user)->get(route('profile'))->assertRedirect(route('password.force'));
    $this->actingAs($user)->get(route('home'))->assertRedirect(route('password.force'));
    $this->actingAs($user)->get(route('password.force'))->assertOk()->assertSee('Set your password');
});

test('setting a new password clears the flag and lands on the dashboard', function () {
    $user = hospitalAdmin();
    $user->forceFill(['must_change_password' => true])->save();

    Livewire::actingAs($user)->test(SetPassword::class)
        ->set('password', 'Brand-new-pass-1')->set('password_confirmation', 'Brand-new-pass-1')
        ->call('save')->assertHasNoErrors()->assertRedirect(route('home'));

    $fresh = $user->fresh();
    expect($fresh->must_change_password)->toBeFalse()->and(Hash::check('Brand-new-pass-1', $fresh->password))->toBeTrue();
    $this->actingAs($fresh)->get(route('dashboard'))->assertOk();
});

test('a weak or mismatched password is rejected', function () {
    $user = hospitalAdmin();
    $user->forceFill(['must_change_password' => true])->save();

    Livewire::actingAs($user)->test(SetPassword::class)
        ->set('password', 'short')->set('password_confirmation', 'short')->call('save')->assertHasErrors(['password']);
    Livewire::actingAs($user)->test(SetPassword::class)
        ->set('password', 'Brand-new-pass-1')->set('password_confirmation', 'different-pass-1')->call('save')->assertHasErrors(['password']);
    expect($user->fresh()->must_change_password)->toBeTrue();
});

test('unflagged users are not affected', function () {
    $this->actingAs(hospitalAdmin())->get(route('dashboard'))->assertOk();
    $this->actingAs(hospitalAdmin())->get(route('password.force'))->assertRedirect(route('home'));
});

test('flagged user can sign out from the set-password page', function () {
    $user = hospitalAdmin();
    $user->forceFill(['must_change_password' => true])->save();

    Livewire::actingAs($user)->test(SetPassword::class)->call('logout')->assertRedirect(route('login'));
    $this->assertGuest();
});
