<?php

namespace App\Models;

use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $submission_id
 * @property string $typeform_field_id
 * @property int|null $form_field_id
 * @property string $answer_type
 * @property string|null $value_text
 * @property float|null $value_number
 * @property array|null $value_json
 * @property float|null $points
 * @property float|null $max_points
 * @property-read FormField|null $field
 */
class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use HasFactory;

    protected $fillable = ['submission_id', 'typeform_field_id', 'form_field_id', 'answer_type', 'value_text', 'value_number', 'value_json', 'points', 'max_points'];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'value_number' => 'float',
            'points' => 'float',
            'max_points' => 'float',
        ];
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<FormField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    public function isScored(): bool
    {
        return $this->points !== null && $this->max_points !== null;
    }

    public function fileUrl(): ?string
    {
        return $this->answer_type === 'file_url' ? ($this->value_json['url'] ?? null) : null;
    }
}
