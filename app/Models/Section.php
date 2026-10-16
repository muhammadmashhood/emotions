<?php

namespace App\Models;

use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $form_version_id
 * @property string $name
 * @property string|null $source_ref
 * @property int $display_order
 * @property float $weight
 * @property-read Collection<int, FormField> $fields
 */
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    protected $fillable = ['form_version_id', 'name', 'source_ref', 'display_order', 'weight'];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }

    /** @return BelongsTo<FormVersion, $this> */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /** @return HasMany<FormField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('display_order');
    }
}
