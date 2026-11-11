<?php

namespace Database\Factories;

use App\Models\Submission;
use App\Models\SubmissionAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<SubmissionAttachment>
 */
class SubmissionAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'answer_typeform_field_id' => null,
            'source' => SubmissionAttachment::SOURCE_UPLOAD,
            'disk' => 'local',
            'path' => 'attachments/'.fake()->uuid().'/doc.pdf',
            'original_name' => 'doc.pdf',
            'mime' => 'application/pdf',
            'size' => 12,
            'uploaded_by' => null,
        ];
    }

    /** A 1×1 PNG written to the (faked) local disk. */
    public function png(): static
    {
        return $this->state(function () {
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
            $path = 'attachments/'.fake()->uuid().'/photo.png';
            Storage::disk('local')->put($path, $png);

            return ['path' => $path, 'original_name' => 'photo.png', 'mime' => 'image/png', 'size' => strlen($png)];
        });
    }
}
