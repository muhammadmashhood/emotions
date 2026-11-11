<?php

namespace App\Models;

use Database\Factories\SubmissionAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file that belongs to an evaluation: either a Typeform file-upload answer copied to our disk at ingest,
 * or a supporting document uploaded by eMotion staff.
 *
 * @property int $id
 * @property int $submission_id
 * @property string|null $answer_typeform_field_id
 * @property string $source typeform|upload
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int|null $size
 * @property int|null $uploaded_by
 * @property-read Submission $submission
 * @property-read User|null $uploader
 */
class SubmissionAttachment extends Model
{
    /** @use HasFactory<SubmissionAttachmentFactory> */
    use HasFactory;

    public const SOURCE_TYPEFORM = 'typeform';

    public const SOURCE_UPLOAD = 'upload';

    /** Larger images are not inlined into PDFs. */
    public const MAX_INLINE_BYTES = 4 * 1024 * 1024;

    protected $fillable = ['submission_id', 'answer_typeform_field_id', 'source', 'disk', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<Submission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function isUpload(): bool
    {
        return $this->source === self::SOURCE_UPLOAD;
    }

    /** Base64 data URI for embedding in a PDF; null for non-images, oversized or missing files. */
    public function dataUri(): ?string
    {
        if (! $this->isImage() || ($this->size ?? 0) > self::MAX_INLINE_BYTES) {
            return null;
        }
        $disk = Storage::disk($this->disk);
        if (! $disk->exists($this->path)) {
            return null;
        }

        return 'data:'.$this->mime.';base64,'.base64_encode((string) $disk->get($this->path));
    }
}
