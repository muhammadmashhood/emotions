<?php

use App\Domain\Users\InvitationService;
use App\Enums\Role;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Shared\UserManagement;
use App\Mail\UserInvitationMail;
use App\Models\Hospital;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(fn () => Mail::fake());

test('super admin invites a hospital admin and the invitee accepts', function () {
    $hospital = Hospital::factory()->create();
    $admin = superAdmin();

    Livewire::actingAs($admin)
        ->test(UserManagement::class, ['hospital' => $hospital])
        ->set('inviteEmail', 'Doctor@Example.com')
        ->set('inviteRole', 'hospital_admin')
        ->call('invite')
        ->assertHasNoErrors();

    $invitation = UserInvitation::query()->where('email', 'doctor@example.com')->firstOrFail();
    expect($invitation->role)->toBe(Role::HospitalAdmin)
        ->and($invitation->hospital_id)->toBe($hospital->id)
        ->and($invitation->expires_at->diffInHours(now()->addHours(72)))->toBeLessThan(1);

    $acceptUrl = null;
    Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$acceptUrl) {
        $acceptUrl = $mail->acceptUrl;

        return $mail->hasTo('doctor@example.com');
    });

    $token = Str::afterLast($acceptUrl, '/');
    // Only a hash of the token is stored.
    expect(UserInvitation::query()->where('token_hash', $token)->exists())->toBeFalse()
        ->and($invitation->token_hash)->toBe(hash('sha256', $token));

    // The admin was logged in by the Livewire test above; the invitee opens the link as a guest.
    auth()->logout();
    $this->app['auth']->forgetGuards();
    $this->get(route('invitation.accept', $token))->assertOk()->assertSee('Set up your account');

    Livewire::test(AcceptInvitation::class, ['token' => $token])
        ->set('name', 'Dr Example')
        ->set('password', 'StrongPassw0rd!')
        ->set('password_confirmation', 'StrongPassw0rd!')
        ->call('accept')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'doctor@example.com')->firstOrFail();
    expect($user->role)->toBe(Role::HospitalAdmin)
        ->and($user->hospital_id)->toBe($hospital->id)
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.invited', 'hospital_id' => $hospital->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.invitation_accepted', 'user_id' => $user->id]);
});

test('expired invitation link is rejected', function () {
    $token = Str::random(64);
    UserInvitation::factory()->expired()->create(['token_hash' => UserInvitation::hashToken($token)]);

    $this->get(route('invitation.accept', $token))->assertOk()->assertSee('invalid or has expired');

    Livewire::test(AcceptInvitation::class, ['token' => $token])
        ->set('name', 'X')
        ->set('password', 'StrongPassw0rd!')
        ->set('password_confirmation', 'StrongPassw0rd!')
        ->call('accept')
        ->assertHasErrors(['token']);

    expect(User::query()->count())->toBe(0);
});

test('an accepted invitation cannot be used twice', function () {
    $token = Str::random(64);
    UserInvitation::factory()->create(['token_hash' => UserInvitation::hashToken($token), 'email' => 'once@example.com']);

    $service = app(InvitationService::class);
    $service->accept($token, 'Once', 'StrongPassw0rd!');

    expect(fn () => $service->accept($token, 'Twice', 'StrongPassw0rd!'))
        ->toThrow(ValidationException::class);
    expect(User::query()->where('email', 'once@example.com')->count())->toBe(1);
});

test('hospital admin can invite into own hospital only, and never a super admin', function () {
    $own = Hospital::factory()->create();
    $other = Hospital::factory()->create();
    $admin = hospitalAdmin($own);

    Livewire::actingAs($admin)
        ->test(UserManagement::class, ['hospital' => $own])
        ->set('inviteEmail', 'colleague@example.com')
        ->set('inviteRole', 'hospital_viewer')
        ->call('invite')
        ->assertHasNoErrors();
    expect(UserInvitation::query()->where('email', 'colleague@example.com')->value('hospital_id'))->toBe($own->id);

    // Mounting the component for another hospital is forbidden outright.
    Livewire::actingAs($admin)
        ->test(UserManagement::class, ['hospital' => $other])
        ->assertForbidden();

    // Even if the role value is tampered with, super_admin is rejected by validation + policy.
    Livewire::actingAs($admin)
        ->test(UserManagement::class, ['hospital' => $own])
        ->set('inviteEmail', 'boss@example.com')
        ->set('inviteRole', 'super_admin')
        ->call('invite')
        ->assertHasErrors(['inviteRole']);
    expect(UserInvitation::query()->where('email', 'boss@example.com')->exists())->toBeFalse();

    // Service-level guard: policy denies cross-hospital invite.
    expect($admin->can('invite', [User::class, Role::HospitalViewer, $other]))->toBeFalse()
        ->and($admin->can('invite', [User::class, Role::SuperAdmin, null]))->toBeFalse()
        ->and($admin->can('invite', [User::class, Role::HospitalViewer, $own]))->toBeTrue();
});

test('super admin can invite another super admin from the users screen', function () {
    Livewire::actingAs(superAdmin())
        ->test(UsersIndex::class)
        ->set('inviteEmail', 'second@example.com')
        ->set('inviteRole', 'super_admin')
        ->set('inviteHospital', '')
        ->call('invite')
        ->assertHasNoErrors();

    $invitation = UserInvitation::query()->where('email', 'second@example.com')->firstOrFail();
    expect($invitation->role)->toBe(Role::SuperAdmin)->and($invitation->hospital_id)->toBeNull();
});

test('inviting an existing user email fails', function () {
    $hospital = Hospital::factory()->create();
    $existing = viewer($hospital);

    Livewire::actingAs(superAdmin())
        ->test(UserManagement::class, ['hospital' => $hospital])
        ->set('inviteEmail', $existing->email)
        ->set('inviteRole', 'hospital_viewer')
        ->call('invite')
        ->assertHasErrors(['email']);
});

test('hospital admin can deactivate colleagues but not themselves, and changes are audited', function () {
    $hospital = Hospital::factory()->create();
    $admin = hospitalAdmin($hospital);
    $colleague = viewer($hospital);

    $component = Livewire::actingAs($admin)->test(UserManagement::class, ['hospital' => $hospital]);

    $component->call('deactivate', $colleague->id);
    expect($colleague->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivated', 'subject_id' => $colleague->id, 'user_id' => $admin->id]);

    $component->call('reactivate', $colleague->id);
    expect($colleague->fresh()->is_active)->toBeTrue();

    $component->call('changeRole', $colleague->id, 'hospital_admin');
    expect($colleague->fresh()->role)->toBe(Role::HospitalAdmin);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed', 'subject_id' => $colleague->id]);

    $component->call('deactivate', $admin->id)->assertForbidden();
    expect($admin->fresh()->is_active)->toBeTrue();
});

test('hospital admin cannot touch users of another hospital', function () {
    $own = Hospital::factory()->create();
    $other = Hospital::factory()->create();
    $admin = hospitalAdmin($own);
    $stranger = viewer($other);

    expect(fn () => Livewire::actingAs($admin)
        ->test(UserManagement::class, ['hospital' => $own])
        ->call('deactivate', $stranger->id))
        ->toThrow(ModelNotFoundException::class);

    expect($stranger->fresh()->is_active)->toBeTrue();
});

test('resending an invitation issues a new token and sends another email', function () {
    $hospital = Hospital::factory()->create();
    $admin = superAdmin();
    $service = app(InvitationService::class);
    $first = $service->invite('again@example.com', Role::HospitalViewer, $hospital, $admin);

    Livewire::actingAs($admin)
        ->test(UserManagement::class, ['hospital' => $hospital])
        ->call('resend', $first->id);

    $pending = UserInvitation::query()->where('email', 'again@example.com')->whereNull('accepted_at')->get();
    expect($pending)->toHaveCount(1)
        ->and($pending->first()->token_hash)->not->toBe($first->token_hash);
    Mail::assertSent(UserInvitationMail::class, 2);
});
