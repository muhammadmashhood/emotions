<?php

use App\Domain\Users\UserManager;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Hospital;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

test('super admin creates a hospital admin with a temporary password', function () {
    $hospital = Hospital::factory()->create();
    UserInvitation::factory()->create(['email' => 'new@h.ae', 'hospital_id' => $hospital->id, 'role' => Role::HospitalViewer]);

    $user = app(UserManager::class)->create('New Person', 'New@h.ae ', Role::HospitalAdmin, $hospital, 'Temp-pass-1234', superAdmin());

    expect($user->email)->toBe('new@h.ae')
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->role)->toBe(Role::HospitalAdmin)
        ->and(Hash::check('Temp-pass-1234', $user->password))->toBeTrue();
    $this->assertDatabaseMissing('user_invitations', ['email' => 'new@h.ae']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'subject_id' => $user->id]);
    expect(AuditLog::query()->where('action', 'user.created')->firstOrFail()->changes)->not->toHaveKey('password');
});

test('creation applies the same rules as invitations', function () {
    $hospital = Hospital::factory()->create();
    $admin = superAdmin();
    $m = app(UserManager::class);

    expect(fn () => $m->create('A', $admin->email, Role::HospitalViewer, $hospital, 'Temp-pass-1234', $admin))->toThrow(ValidationException::class, 'already exists');
    expect(fn () => $m->create('A', 'a@h.ae', Role::SuperAdmin, $hospital, 'Temp-pass-1234', $admin))->toThrow(ValidationException::class, 'do not belong');
    expect(fn () => $m->create('A', 'a@h.ae', Role::HospitalViewer, null, 'Temp-pass-1234', $admin))->toThrow(ValidationException::class, 'hospital is required');
    expect(fn () => $m->create('A', 'a@h.ae', Role::HospitalViewer, $hospital, 'short', $admin))->toThrow(ValidationException::class);
    expect(User::query()->where('email', 'a@h.ae')->exists())->toBeFalse();
});

test('policy: hospital admin may create only hospital roles in own hospital', function () {
    $h = Hospital::factory()->create();
    $other = Hospital::factory()->create();
    $admin = hospitalAdmin($h);

    expect($admin->can('create', [User::class, Role::HospitalViewer, $h]))->toBeTrue()
        ->and($admin->can('create', [User::class, Role::HospitalViewer, $other]))->toBeFalse()
        ->and($admin->can('create', [User::class, Role::SuperAdmin, null]))->toBeFalse()
        ->and(superAdmin()->can('create', [User::class, Role::QualityAssurance, null]))->toBeTrue();
});
