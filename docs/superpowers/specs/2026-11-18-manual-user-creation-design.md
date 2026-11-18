# Manual user creation design

**Date:** 2026-11-18

## Intent

Invitation emails do not always arrive (Gmail drops mail from the SiteGround server). An admin needs a second way to onboard someone: create the account directly with a name, email, role and a temporary password, and hand that password over themselves.

## Goals

- "Create user" alongside "Invite" on the eMotion Users page (Super Admin) and on a hospital's Team tab (hospital admin), under the same permission rules as invitations.
- Temporary password typed or generated; shown once after saving; never emailed.
- The new user must set their own password on first sign-in before seeing anything else.
- Audit entry `user.created` with the actor.

## Non-goals

- Replacing invitations. Both paths stay.
- Password expiry or rotation policies.

## Anti-goals

- Storing or redisplaying the temporary password after the confirmation screen.
- Letting a hospital admin create eMotion staff or users in another hospital.

## Approaches considered

1. **Create directly with a `must_change_password` flag and a gate middleware (chosen).** Small schema change, one middleware, one page. Clear user experience.
2. Create the user and send a password-reset link. Depends on email again, which is the problem being solved.
3. Create without forcing a change. Smallest change, but a password shared over WhatsApp or a call would stay in use (user chose forced change).

## Design

- Migration: `users.must_change_password` boolean, default false.
- `UserManager::create(name, email, role, hospital, password, by)`: same role/hospital/duplicate rules as `InvitationService::invite`; removes any pending invitation for that email; creates an active user with the flag set; audits `user.created`.
- `UserPolicy::create` delegates to `invite`.
- Middleware `EnsurePasswordIsChanged` in the web group: a signed-in user with the flag set is redirected to `password.force` for every request except that page, the Livewire update endpoint and sign-out.
- Page `password.force` (Livewire, guest layout): new password + confirmation, `Password::defaults()`; saving clears the flag and sends the user to their home. Has a sign-out button.
- Profile password form also clears the flag.
- Users page modal and Team tab form gain a "Set a temporary password" mode: name, email, hospital/role as today, password field with a Generate button (12 characters, letters and digits). After saving, a panel shows the password once with a copy button and the warning that it will not be shown again.

## Builder's calls

- Generated passwords avoid symbols so they can be read out over the phone.
- A user created this way shows no "Invited" row; they appear in the users table immediately with status Active.
