<?php

namespace App\Domain\Users;

use App\Domain\Audit\AuditLogger;
use App\Enums\Role;
use App\Mail\UserInvitationMail;
use App\Models\Hospital;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class InvitationService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Create (or re-create) an invitation and email the signed link.
     * Authorisation is checked by the caller through UserPolicy.
     */
    public function invite(string $email, Role $role, ?Hospital $hospital, User $invitedBy): UserInvitation
    {
        $email = Str::lower(trim($email));

        if (! $role->isHospitalRole() && $hospital !== null) {
            throw ValidationException::withMessages(['role' => 'eMotion staff do not belong to a hospital.']);
        }
        if ($role->isHospitalRole() && $hospital === null) {
            throw ValidationException::withMessages(['hospital_id' => 'A hospital is required for this role.']);
        }
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
        }

        $token = Str::random(64);

        // One pending invitation per email: replace any existing one — but only once the email is actually handed
        // to the mail server. A transport failure rolls the whole thing back, so a previously sent link keeps working.
        try {
            $invitation = DB::transaction(function () use ($email, $hospital, $role, $invitedBy, $token): UserInvitation {
                UserInvitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

                $invitation = UserInvitation::query()->create([
                    'email' => $email,
                    'hospital_id' => $hospital?->id,
                    'role' => $role,
                    'token_hash' => UserInvitation::hashToken($token),
                    'invited_by' => $invitedBy->id,
                    'expires_at' => now()->addHours(72),
                ]);

                Mail::to($email)->send(new UserInvitationMail($invitation, $this->acceptUrl($token)));

                return $invitation;
            });
        } catch (TransportExceptionInterface $e) {
            Log::warning('invitation.mail_failed', ['email' => $email, 'error' => $e->getMessage()]);

            throw new InvitationMailFailed(InvitationMailFailed::MESSAGE, 0, $e);
        }

        $this->audit->log('user.invited', $invitation, ['email' => $email, 'role' => $role->value], $hospital, $invitedBy);

        return $invitation;
    }

    public function resend(UserInvitation $invitation, User $by): UserInvitation
    {
        $hospital = $invitation->hospital;

        return $this->invite($invitation->email, $invitation->role, $hospital, $by);
    }

    public function findByToken(string $token): ?UserInvitation
    {
        return UserInvitation::query()->where('token_hash', UserInvitation::hashToken($token))->first();
    }

    /**
     * Accept an invitation: create the user with the invited role and hospital.
     */
    public function accept(string $token, string $name, string $password): User
    {
        $invitation = $this->findByToken($token);

        if ($invitation === null || $invitation->isAccepted() || $invitation->isExpired()) {
            throw ValidationException::withMessages(['token' => 'This invitation link is invalid or has expired.']);
        }

        return DB::transaction(function () use ($invitation, $name, $password): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'role' => $invitation->role,
                'hospital_id' => $invitation->hospital_id,
                'is_active' => true,
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            $this->audit->log('user.invitation_accepted', $user, ['email' => $user->email], $user->hospital_id, $user);

            return $user;
        });
    }

    public function acceptUrl(string $token): string
    {
        return route('invitation.accept', ['token' => $token]);
    }
}
