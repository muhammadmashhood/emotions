<?php

use App\Models\Hospital;
use App\Models\User;
use Livewire\Volt\Volt;

test('hospital users get 403 on every admin route', function (string $role) {
    $hospital = Hospital::factory()->create();
    $user = $role === 'admin' ? hospitalAdmin($hospital) : viewer($hospital);

    $routes = [
        route('admin.overview'),
        route('admin.hospitals.index'),
        route('admin.hospitals.create'),
        route('admin.hospitals.show', $hospital),
        route('admin.users.index'),
    ];

    foreach ($routes as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }
})->with(['admin', 'viewer']);

test('super admin can open admin routes', function () {
    $hospital = Hospital::factory()->create();
    $admin = superAdmin();

    $this->actingAs($admin)->get(route('admin.overview'))->assertOk();
    $this->actingAs($admin)->get(route('admin.hospitals.index'))->assertOk()->assertSee($hospital->name);
    $this->actingAs($admin)->get(route('admin.hospitals.show', $hospital))->assertOk();
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
});

test('only hospital admins can open the team page', function () {
    $hospital = Hospital::factory()->create();

    $this->actingAs(hospitalAdmin($hospital))->get(route('team'))->assertOk();
    $this->actingAs(viewer($hospital))->get(route('team'))->assertForbidden();
    $this->actingAs(superAdmin())->get(route('team'))->assertForbidden();
});

test('inactive user cannot log in', function () {
    $user = User::factory()->viewer()->inactive()->create();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors(['form.email']);

    $this->assertGuest();
});

test('user of an inactive hospital cannot log in', function () {
    $hospital = Hospital::factory()->inactive()->create();
    $user = User::factory()->viewer($hospital)->create();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors(['form.email']);

    $this->assertGuest();
});

test('a user deactivated mid-session is logged out on the next request', function () {
    $user = viewer();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->update(['is_active' => false]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('successful login records last_login_at and an audit entry', function () {
    $user = viewer();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasNoErrors();

    expect($user->fresh()->last_login_at)->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id, 'hospital_id' => $user->hospital_id]);
});

test('failed login is audited', function () {
    $user = viewer();

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'nope')
        ->call('login')
        ->assertHasErrors();

    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed']);
});

test('root redirects by role', function () {
    $this->get('/')->assertRedirect(route('login'));
    $this->actingAs(superAdmin())->get('/')->assertRedirect(route('admin.overview'));
    $this->actingAs(viewer())->get('/')->assertRedirect(route('dashboard'));
});
