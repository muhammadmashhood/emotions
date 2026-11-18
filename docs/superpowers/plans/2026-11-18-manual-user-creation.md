# Manual User Creation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admins can create a user directly with a temporary password; the user must set their own password at first sign-in.

**Architecture:** A `must_change_password` flag on users, a `UserManager::create` domain method mirroring the invite rules, a web-group middleware that funnels flagged users to a set-password page, and a "temporary password" mode on the two existing invite forms.

**Tech Stack:** Laravel 11, Livewire 3 (class components + Volt auth pages), Pest on MySQL (`docker run -d --name emotions-test-mysql -p 127.0.0.1:3307:3306 -e MYSQL_ROOT_PASSWORD=secret -e MYSQL_DATABASE=emotion_test mysql:8`), phpstan level 6, pint.

**Spec:** `docs/superpowers/specs/2026-11-18-manual-user-creation-design.md`

## Global Constraints

- Permission rules identical to invitations (`UserPolicy::invite`).
- The temporary password is never persisted in clear, logged, audited or emailed.
- `vendor/bin/phpstan analyse` and `vendor/bin/pint --test` clean before each commit.

## Review Focus

1. A flagged user hitting any Livewire action on another page is still redirected, not served. (Task 2 test `flagged user is redirected from every page`)
2. Creating a user whose email has a pending invitation removes that invitation so the list does not show both. (Task 1 test)
3. A hospital admin creating a user cannot pick another hospital or a staff role. (Task 1 + Task 4 tests)
4. The password confirmation panel disappears after navigation and the password is not in the Livewire snapshot afterwards. (Task 3 test asserts the property is reset after `dismissCreated`)
5. Weak temporary password is rejected by `Password::defaults()`. (Task 1 test)

---

### Task 1: Flag, domain method, policy, audit label

**Files:**
- Create: `database/migrations/2026_11_18_000001_add_must_change_password_to_users.php`
- Modify: `app/Models/User.php` (fillable, casts, docblock), `app/Domain/Users/UserManager.php`, `app/Policies/UserPolicy.php`, `app/Models/AuditLog.php:62`
- Test: `tests/Feature/Admin/UserCreationTest.php` (create)

**Interfaces:**
- Produces: `UserManager::create(string $name, string $email, Role $role, ?Hospital $hospital, string $password, User $by): User` throwing `ValidationException` keyed `email` / `role` / `hospital_id` / `password`; `UserPolicy::create(User $user, Role $role, ?Hospital $hospital): bool`; `User::$must_change_password` bool.

- [ ] **Step 1: Failing tests** in `tests/Feature/Admin/UserCreationTest.php`:

```php
test('super admin creates a hospital admin with a temporary password', function () {
    $hospital = Hospital::factory()->create();
    UserInvitation::factory()->create(['email' => 'new@h.ae', 'hospital_id' => $hospital->id, 'role' => Role::HospitalViewer]);
    $user = app(UserManager::class)->create('New Person', 'New@h.ae ', Role::HospitalAdmin, $hospital, 'Temp-pass-1234', superAdmin());
    expect($user->email)->toBe('new@h.ae')->and($user->must_change_password)->toBeTrue()->and($user->is_active)->toBeTrue()
        ->and(Hash::check('Temp-pass-1234', $user->password))->toBeTrue();
    $this->assertDatabaseMissing('user_invitations', ['email' => 'new@h.ae']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'subject_id' => $user->id]);
    expect(AuditLog::query()->where('action', 'user.created')->first()->changes)->not->toHaveKey('password');
});
test('creation applies the same rules as invitations', function () {
    $hospital = Hospital::factory()->create();
    $m = app(UserManager::class);
    expect(fn () => $m->create('A', superAdmin()->email, Role::HospitalViewer, $hospital, 'Temp-pass-1234', superAdmin()))->toThrow(ValidationException::class, 'already exists');
    expect(fn () => $m->create('A', 'a@h.ae', Role::SuperAdmin, $hospital, 'Temp-pass-1234', superAdmin()))->toThrow(ValidationException::class, 'do not belong');
    expect(fn () => $m->create('A', 'a@h.ae', Role::HospitalViewer, null, 'Temp-pass-1234', superAdmin()))->toThrow(ValidationException::class, 'hospital is required');
    expect(fn () => $m->create('A', 'a@h.ae', Role::HospitalViewer, $hospital, 'short', superAdmin()))->toThrow(ValidationException::class);
});
test('policy: hospital admin may create only hospital roles in own hospital', function () {
    $h = Hospital::factory()->create(); $other = Hospital::factory()->create(); $admin = hospitalAdmin($h);
    expect($admin->can('create', [User::class, Role::HospitalViewer, $h]))->toBeTrue()
        ->and($admin->can('create', [User::class, Role::HospitalViewer, $other]))->toBeFalse()
        ->and($admin->can('create', [User::class, Role::SuperAdmin, null]))->toBeFalse()
        ->and(superAdmin()->can('create', [User::class, Role::QualityAssurance, null]))->toBeTrue();
});
```

- [ ] **Step 2: Run** `vendor/bin/pest tests/Feature/Admin/UserCreationTest.php` — Expected: FAIL (method/column missing).
- [ ] **Step 3: Implement.** Migration adds `boolean('must_change_password')->default(false)->after('is_active')`. `UserManager::create` validates the password with `Validator::make(['password' => $password], ['password' => ['required', Password::defaults()]])->validate()`, lower-trims the email, applies the three invite rules with the same messages, then in a transaction deletes pending invitations for the email, creates the user (`must_change_password => true`), audits `user.created` with `['email' => ..., 'role' => ...]`. Policy `create` returns `$this->invite(...)`. AuditLog label `'user.created' => 'Created user'`.
- [ ] **Step 4: Run** the test file — Expected: PASS. Then `vendor/bin/phpstan analyse && vendor/bin/pint`.
- [ ] **Step 5: Commit** `users: UserManager::create with temporary password and must_change_password flag`.

### Task 2: Forced password change

**Files:**
- Create: `app/Http/Middleware/EnsurePasswordIsChanged.php`, `app/Livewire/Auth/SetPassword.php`, `resources/views/livewire/auth/set-password.blade.php`
- Modify: `bootstrap/app.php:25` (append to web group after `EnsureUserIsActive`), `routes/web.php` (auth group: `Route::get('set-password', SetPassword::class)->name('password.force')`), `resources/views/livewire/profile/update-password-form.blade.php:31` (also set `must_change_password => false`)
- Test: `tests/Feature/Auth/ForcedPasswordChangeTest.php`

**Interfaces:**
- Consumes: `User::$must_change_password` from Task 1.
- Produces: route name `password.force`.

- [ ] **Step 1: Failing tests**:

```php
test('flagged user is redirected from every page to set-password', function () {
    $user = hospitalAdmin(); $user->forceFill(['must_change_password' => true])->save();
    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.force'));
    $this->actingAs($user)->get(route('profile'))->assertRedirect(route('password.force'));
    $this->actingAs($user)->get(route('password.force'))->assertOk()->assertSee('Set your password');
});
test('setting a new password clears the flag and lands on the dashboard', function () {
    $user = hospitalAdmin(); $user->forceFill(['must_change_password' => true])->save();
    Livewire::actingAs($user)->test(SetPassword::class)
        ->set('password', 'Brand-new-pass-1')->set('password_confirmation', 'Brand-new-pass-1')
        ->call('save')->assertHasNoErrors()->assertRedirect(route('home'));
    expect($user->fresh()->must_change_password)->toBeFalse()->and(Hash::check('Brand-new-pass-1', $user->fresh()->password))->toBeTrue();
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
});
test('unflagged users are not affected', function () {
    $this->actingAs(hospitalAdmin())->get(route('dashboard'))->assertOk();
    $this->actingAs(hospitalAdmin())->get(route('password.force'))->assertRedirect(route('home'));
});
```

- [ ] **Step 2: Run** — Expected: FAIL (route not defined).
- [ ] **Step 3: Implement.** Middleware: if `$request->user()?->must_change_password` and not (`routeIs('password.force')` or `is('livewire/*')`) → `redirect()->route('password.force')`. Component `SetPassword` (`#[Layout('layouts.guest')]`): `mount` redirects to `home` when the flag is not set; properties `password`, `password_confirmation`; `save()` validates `['required', 'string', Password::defaults(), 'confirmed']`, updates password + flag false, regenerates session, `redirect(route('home'), navigate: false)`; `logout(Logout $logout)` calls the action and redirects to login. View mirrors the login page styling: heading "Set your password", sub-line "Your account was created with a temporary password. Choose your own before continuing.", two fields, Save button, "Sign out" link.
- [ ] **Step 4: Run** the test file — PASS; then `vendor/bin/pest tests/Feature/Auth` — all PASS; phpstan + pint.
- [ ] **Step 5: Commit** `auth: force a password change at first sign-in for manually created users`.

### Task 3: Users page: create mode in the invite modal

**Files:**
- Modify: `app/Livewire/Admin/Users/Index.php`, `resources/views/livewire/admin/users/index.blade.php:88-126`
- Test: `tests/Feature/Admin/UserCreationTest.php` (append)

**Interfaces:**
- Consumes: `UserManager::create`, policy `create`.
- Produces: component API `mode` ('invite'|'create'), `createName`, `createPassword`, `createdPassword` (?string), `createdEmail` (?string), methods `generatePassword()`, `create(UserManager $users)`, `dismissCreated()`.

- [ ] **Step 1: Failing test**:

```php
test('users page creates a user with a temporary password and shows it once', function () {
    $hospital = Hospital::factory()->create();
    $c = Livewire::actingAs(superAdmin())->test(UsersIndex::class)
        ->set('mode', 'create')->set('createName', 'Sara Ali')->set('inviteEmail', 'sara@h.ae')
        ->set('inviteHospital', $hospital->uuid)->set('inviteRole', 'hospital_viewer')
        ->call('generatePassword');
    $password = $c->get('createPassword');
    expect(strlen($password))->toBe(12);
    $c->call('create')->assertHasNoErrors()->assertSee('shown only once')->assertSee($password);
    $this->assertDatabaseHas('users', ['email' => 'sara@h.ae', 'name' => 'Sara Ali', 'must_change_password' => true]);
    $c->call('dismissCreated');
    expect($c->get('createdPassword'))->toBeNull()->and($c->get('createPassword'))->toBe('');
});
```

- [ ] **Step 2: Run** `--filter "users page creates"` — FAIL.
- [ ] **Step 3: Implement.** Validation: `createName` required max 255, `inviteEmail`, `inviteRole`, `inviteHospital` as in `invite()`, `createPassword` required string. `generatePassword()` sets `Str::password(12, symbols: false)`. `create()` authorises `create`, calls the manager, catches `ValidationException` into field errors (`addError('inviteEmail'|'createPassword'|…)`), then sets `createdPassword`/`createdEmail`, resets the form fields, flashes "User created.", dispatches `invite-sent` to close the modal. View: a two-button segmented toggle at the top of the modal ("Email an invitation" / "Set a temporary password") bound to `mode` with `wire:model.live`; in create mode the sub-line reads "Share the password with them yourself. They must change it when they first sign in."; show the Name field and the Password field (text input, with a "Generate" button) and submit calls `create` with label "Create user". After the modal closes, a green panel above the table shows "Temporary password for {email}: <code>{password}</code>", "It is shown only once. Copy it now.", a Copy button (`navigator.clipboard.writeText`) and a Done button calling `dismissCreated`.
- [ ] **Step 4: Run** the test file — PASS; phpstan + pint.
- [ ] **Step 5: Commit** `users: create a user with a temporary password from the Users page`.

### Task 4: Team tab: create mode

**Files:**
- Modify: `app/Livewire/Shared/UserManagement.php`, `resources/views/livewire/shared/user-management.blade.php:1-25`
- Test: `tests/Feature/Admin/UserCreationTest.php` (append)

**Interfaces:** same component API as Task 3, hospital fixed to `$this->hospital`.

- [ ] **Step 1: Failing test**:

```php
test('hospital admin creates a colleague with a temporary password on the team tab', function () {
    $hospital = Hospital::factory()->create();
    $c = Livewire::actingAs(hospitalAdmin($hospital))->test(UserManagement::class, ['hospital' => $hospital])
        ->set('mode', 'create')->set('createName', 'Omar')->set('inviteEmail', 'omar@h.ae')->set('inviteRole', 'hospital_viewer')
        ->set('createPassword', 'Temp-pass-1234')->call('create')->assertHasNoErrors()->assertSee('Temp-pass-1234');
    expect(User::query()->where('email', 'omar@h.ae')->first()->hospital_id)->toBe($hospital->id);
    Livewire::actingAs(hospitalAdmin($hospital))->test(UserManagement::class, ['hospital' => $hospital])
        ->set('mode', 'create')->set('createName', 'X')->set('inviteEmail', 'x@h.ae')->set('inviteRole', 'super_admin')
        ->set('createPassword', 'Temp-pass-1234')->call('create')->assertHasErrors(['inviteRole']);
});
```

- [ ] **Step 2: Run** — FAIL. **Step 3: Implement** as Task 3 with the hospital fixed and roles limited to `Role::hospitalRoles()`; the form gets the same toggle, Name and Password fields appear in create mode, and the one-time panel sits under the form. **Step 4:** test file + `tests/Feature/Admin/InvitationTest.php` PASS; phpstan + pint. **Step 5: Commit** `team: create a colleague with a temporary password`.

### Task 5: Runbook and full verification

- [ ] Append to `docs/RUNBOOK.md` a section "Creating a user without email" (three lines: where, what the person sees at first sign-in, what to do if they lose the password: reset it by creating… no — use "Forgot password" which emails, or deactivate and create again).
- [ ] `vendor/bin/pest && vendor/bin/phpstan analyse && vendor/bin/pint --test` — all green. Commit `docs: creating a user without email`.
