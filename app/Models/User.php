<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Role $role
 * @property int|null $hospital_id
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property-read Hospital|null $hospital
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'hospital_id',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $role = $user->role;

            if ($role === Role::SuperAdmin && $user->hospital_id !== null) {
                throw new InvalidArgumentException('A super admin cannot belong to a hospital.');
            }

            if ($role !== Role::SuperAdmin && $user->hospital_id === null) {
                throw new InvalidArgumentException('Hospital users must belong to a hospital.');
            }
        });
    }

    /** @return BelongsTo<Hospital, $this> */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isHospitalAdmin(): bool
    {
        return $this->role === Role::HospitalAdmin;
    }

    public function belongsToHospital(Hospital|int $hospital): bool
    {
        $id = $hospital instanceof Hospital ? $hospital->id : $hospital;

        return $this->hospital_id === $id;
    }

    /**
     * Whether this account (and its hospital) is allowed to log in.
     */
    public function canAccess(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return (bool) $this->hospital?->is_active;
    }
}
