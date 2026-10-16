<?php

namespace App\Models;

use App\Enums\Rag;
use App\Enums\SubmissionSource;
use App\Enums\SubmissionStatus;
use App\Models\Concerns\BelongsToHospital;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $form_id
 * @property int $form_version_id
 * @property int $hospital_id
 * @property int|null $branch_id
 * @property string $response_token
 * @property Carbon|null $submitted_at
 * @property Carbon|null $landed_at
 * @property bool $is_partial
 * @property SubmissionStatus $status
 * @property SubmissionSource $source
 * @property array $raw
 * @property array|null $info
 * @property float|null $points
 * @property float|null $max_points
 * @property float|null $overall_pct
 * @property Rag|null $rag
 * @property Carbon|null $scored_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 * @property-read Form $form
 * @property-read FormVersion $formVersion
 * @property-read Branch|null $branch
 * @property-read Collection<int, Answer> $answers
 * @property-read Collection<int, SubmissionSectionScore> $sectionScores
 */
class Submission extends Model
{
    use BelongsToHospital;

    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'form_id', 'form_version_id', 'hospital_id', 'branch_id', 'response_token', 'submitted_at', 'landed_at',
        'is_partial', 'status', 'source', 'raw', 'info', 'points', 'max_points', 'overall_pct', 'rag', 'scored_at',
        'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'landed_at' => 'datetime',
            'is_partial' => 'boolean',
            'status' => SubmissionStatus::class,
            'source' => SubmissionSource::class,
            'raw' => 'array',
            'info' => 'array',
            'points' => 'float',
            'max_points' => 'float',
            'overall_pct' => 'float',
            'rag' => Rag::class,
            'scored_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Submission $submission): void {
            $submission->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Route model binding: hospital users may only resolve their own hospital's evaluations.
     * Anything else is a 404 (never a 403, which would leak existence).
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $user = auth()->user();
        $query = self::query()->where($field ?? $this->getRouteKeyName(), $value);

        if ($user instanceof User) {
            $query = $query->viewableBy($user);
        }

        return $query->first();
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class)->withTrashed();
    }

    /** @return BelongsTo<FormVersion, $this> */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<Answer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /** @return HasMany<SubmissionSectionScore, $this> */
    public function sectionScores(): HasMany
    {
        return $this->hasMany(SubmissionSectionScore::class)->orderBy('display_order');
    }

    /**
     * What hospital users may see: approved and (unless the form includes partials) complete evaluations.
     *
     * @param  Builder<Submission>  $query
     * @return Builder<Submission>
     */
    public function scopeHospitalVisible(Builder $query): Builder
    {
        $partialForms = static::formsIncludingPartials();

        return $query->where('submissions.status', SubmissionStatus::Approved->value)
            ->where(function (Builder $q) use ($partialForms): void {
                $q->where('submissions.is_partial', false);
                if ($partialForms !== []) {
                    $q->orWhereIn('submissions.form_id', $partialForms);
                }
            });
    }

    /**
     * Ids of forms whose settings include partial responses (one tiny query instead of a per-row JSON subquery).
     *
     * @return list<int>
     */
    public static function formsIncludingPartials(): array
    {
        return Form::withTrashed()
            ->whereRaw("JSON_EXTRACT(settings, '$.include_partial') = true")
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Apply the full visibility rule set of section 8 for a given user.
     *
     * @param  Builder<Submission>  $query
     * @return Builder<Submission>
     */
    public function scopeViewableBy(Builder $query, User $user): Builder
    {
        $query->visibleTo($user);

        if (! $user->isSuperAdmin()) {
            $query->hospitalVisible();
        }

        return $query;
    }

    public function infoValue(string $key): ?string
    {
        $value = $this->info[$key] ?? null;

        return $value === null ? null : (string) $value;
    }
}
