<?php

namespace App\Models;

use Database\Factories\FormVersionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $form_id
 * @property int $version_number
 * @property array $definition
 * @property string $definition_hash
 * @property bool $needs_review
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property-read Form $form
 * @property-read Collection<int, Section> $sections
 * @property-read Collection<int, FormField> $fields
 */
class FormVersion extends Model
{
    /** @use HasFactory<FormVersionFactory> */
    use HasFactory;

    protected $fillable = ['form_id', 'version_number', 'definition', 'definition_hash', 'needs_review', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'needs_review' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('display_order');
    }

    /** @return HasMany<FormField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('display_order');
    }

    /** @return HasMany<Submission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
