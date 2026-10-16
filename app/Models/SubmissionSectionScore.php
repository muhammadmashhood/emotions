<?php

namespace App\Models;

use Database\Factories\SubmissionSectionScoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $submission_id
 * @property int $section_id
 * @property string $section_name
 * @property int $display_order
 * @property float $points
 * @property float $max_points
 * @property float|null $pct
 * @property float $weight
 */
class SubmissionSectionScore extends Model
{
    /** @use HasFactory<SubmissionSectionScoreFactory> */
    use HasFactory;

    protected $fillable = ['submission_id', 'section_id', 'section_name', 'display_order', 'points', 'max_points', 'pct', 'weight'];

    protected function casts(): array
    {
        return [
            'points' => 'float',
            'max_points' => 'float',
            'pct' => 'float',
            'weight' => 'float',
        ];
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
