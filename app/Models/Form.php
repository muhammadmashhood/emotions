<?php

namespace App\Models;

use App\Casts\FormSettingsCast;
use App\Domain\Forms\FormSettings;
use App\Enums\FormStatus;
use App\Enums\WebhookStatus;
use App\Models\Concerns\BelongsToHospital;
use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $hospital_id
 * @property int $typeform_connection_id
 * @property string $typeform_form_id
 * @property string $title
 * @property string $webhook_tag
 * @property string $webhook_secret
 * @property WebhookStatus $webhook_status
 * @property Carbon|null $webhook_checked_at
 * @property string|null $webhook_last_error
 * @property int|null $current_version_id
 * @property FormSettings $settings
 * @property FormStatus $status
 * @property Carbon|null $last_synced_at
 * @property int|null $linked_by
 * @property-read FormVersion|null $currentVersion
 * @property-read TypeformConnection $connection
 */
class Form extends Model
{
    use BelongsToHospital;

    /** @use HasFactory<FormFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hospital_id', 'typeform_connection_id', 'typeform_form_id', 'title', 'webhook_tag', 'webhook_secret',
        'webhook_status', 'webhook_checked_at', 'webhook_last_error', 'current_version_id', 'settings', 'status',
        'last_synced_at', 'linked_by',
    ];

    protected $hidden = ['webhook_secret'];

    protected function casts(): array
    {
        return [
            'webhook_secret' => 'encrypted',
            'webhook_status' => WebhookStatus::class,
            'status' => FormStatus::class,
            'settings' => FormSettingsCast::class,
            'webhook_checked_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Form $form): void {
            $form->uuid ??= (string) Str::uuid();
            $form->webhook_tag ??= 'emotion-'.substr($form->uuid, 0, 8);
            $form->webhook_secret ??= Str::random(48);
            if ($form->getAttributes()['settings'] ?? null) {
                return;
            }
            $form->settings = new FormSettings;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<TypeformConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(TypeformConnection::class, 'typeform_connection_id');
    }

    /** @return BelongsTo<FormVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'current_version_id');
    }

    /** @return HasMany<FormVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class)->orderByDesc('version_number');
    }

    /** @return HasMany<Submission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /** @return BelongsTo<User, $this> */
    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FormStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === FormStatus::Active;
    }

    public function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/webhooks/typeform/'.$this->uuid;
    }
}
