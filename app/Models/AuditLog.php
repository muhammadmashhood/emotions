<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
