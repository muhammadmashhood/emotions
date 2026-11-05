<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $hospital_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array|null $changes
 * @property string|null $ip
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Hospital|null $hospital
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['user_id', 'hospital_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip', 'created_at'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** Human label for the action code, e.g. "mapping.updated" → "Saved mapping". */
    public function label(): string
    {
        return match ($this->action) {
            'auth.login' => 'Logged in',
            'auth.login_failed' => 'Failed login',
            'form.linked' => 'Linked form',
            'form.relinked' => 'Re-linked form',
            'form.unlinked' => 'Unlinked form',
            'form.rescored' => 'Re-scored form',
            'form.settings_updated' => 'Updated form settings',
            'form.version_created' => 'New form version',
            'hospital.created' => 'Created hospital',
            'hospital.updated' => 'Updated hospital',
            'mapping.updated' => 'Saved mapping',
            'submission.approved' => 'Approved & published',
            'submission.rejected' => 'Rejected evaluation',
            'submission.rescored' => 'Re-scored evaluation',
            'typeform.connection_saved' => 'Saved Typeform connection',
            'typeform.connection_failed' => 'Typeform connection failed',
            'user.invited' => 'Invited user',
            'user.invitation_accepted' => 'Accepted invitation',
            'user.deactivated' => 'Deactivated user',
            'user.reactivated' => 'Reactivated user',
            'user.role_changed' => 'Changed role',
            'webhook.repaired' => 'Repaired webhook',
            default => Str::ucfirst(str_replace(['.', '_'], ' ', $this->action)),
        };
    }

    /** One-line context: the most telling recorded value plus the hospital, e.g. "OPD survey · Ittefaq Hospital". */
    public function summary(): string
    {
        // getAttribute(): inside the model, $this->changes is Eloquent's own dirty-tracking property.
        $changes = $this->getAttribute('changes');
        $changes = is_array($changes) ? $changes : [];
        $headline = collect(['title', 'email', 'name', 'note'])
            ->map(fn (string $key) => $changes[$key] ?? null)
            ->first(fn ($value) => is_scalar($value) && (string) $value !== '');
        // Sign-ins are about the person, so the hospital (or eMotion staff) is the useful context.
        $subject = match (true) {
            str_starts_with($this->action, 'auth.') => $this->hospital ? null : 'eMotion',
            (bool) $this->subject_type => class_basename((string) $this->subject_type).' #'.$this->subject_id,
            default => null,
        };

        return collect([$headline ?? $subject, $this->hospital?->name])->filter()->unique()->implode(' · ') ?: '—';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
