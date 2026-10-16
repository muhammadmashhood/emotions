<?php

namespace App\Models;

use Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $form_version_id
 * @property string $typeform_field_id
 * @property string|null $typeform_ref
 * @property string $title
 * @property string|null $description
 * @property string $type
 * @property int $section_id
 * @property int $display_order
 * @property bool $is_scored
 * @property int|null $scale_min
 * @property int|null $scale_max
 * @property array|null $choices
 * @property string|null $info_key
 * @property bool $is_new_in_version
 * @property-read Section $section
 */
class FormField extends Model
{
    /** @use HasFactory<FormFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'form_version_id', 'typeform_field_id', 'typeform_ref', 'title', 'description', 'type', 'section_id',
        'display_order', 'is_scored', 'scale_min', 'scale_max', 'choices', 'info_key', 'is_new_in_version',
    ];

    protected function casts(): array
    {
        return [
            'is_scored' => 'boolean',
            'is_new_in_version' => 'boolean',
            'choices' => 'array',
        ];
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<FormVersion, $this> */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function isScale(): bool
    {
        return in_array($this->type, ['opinion_scale', 'rating', 'nps'], true);
    }

    public function scaleLabel(): ?string
    {
        if ($this->scale_min === null || $this->scale_max === null) {
            return null;
        }

        return $this->scale_min.'–'.$this->scale_max;
    }
}
