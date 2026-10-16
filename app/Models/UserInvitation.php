<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserInvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property int|null $hospital_id
 * @property Role $role
 * @property string $token_hash
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property-read Hospital|null $hospital
 * @property-read User|null $inviter
 */
class UserInvitation extends Model
{
    /** @use HasFactory<UserInvitationFactory> */
    use HasFactory;

    protected $fillable = ['email', 'hospital_id', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
