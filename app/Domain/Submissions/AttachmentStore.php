<?php

namespace App\Domain\Submissions;

use App\Domain\Typeform\TypeformClient;
use App\Models\Answer;
use App\Models\Submission;
use App\Models\SubmissionAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps evaluation files on our own private disk: Typeform file answers are copied once at ingest (so the PDF can
 * embed them and they survive token rotation), and staff can add supporting documents.
 */
class AttachmentStore
{
    public const DISK = 'local';

    public static function directory(Submission $submission): string
    {
        return 'attachments/'.$submission->uuid;
    }

    /**
     * Download every file answer that has no stored copy yet. Failures are logged per file, never thrown.
     *
     * @return int number of files stored
     */
    public function syncFromTypeform(Submission $submission): int
    {
        $submission->loadMissing('form.connection', 'answers');
        $answers = $submission->answers->filter(fn (Answer $a) => $a->fileUrl() !== null);
        if ($answers->isEmpty()) {
            return 0;
        }

        $stored = $submission->attachments()->where('source', SubmissionAttachment::SOURCE_TYPEFORM)->pluck('answer_typeform_field_id')->flip();
        $client = TypeformClient::for($submission->form->connection);
        $count = 0;

        foreach ($answers as $answer) {
            if ($stored->has($answer->typeform_field_id)) {
                continue;
            }

            try {
                $response = $client->download((string) $answer->fileUrl());
            } catch (Throwable $e) {
                Log::warning('attachment.download_failed', ['submission_id' => $submission->id, 'field' => $answer->typeform_field_id, 'error' => $e->getMessage()]);

                continue;
            }

            $name = $answer->value_text !== null && $answer->value_text !== '' ? $answer->value_text : 'file';
            $path = self::directory($submission).'/'.$answer->typeform_field_id.'-'.self::safeName($name);
            $body = $response->body();
            Storage::disk(self::DISK)->put($path, $body);

            $submission->attachments()->create([
                'answer_typeform_field_id' => $answer->typeform_field_id,
                'source' => SubmissionAttachment::SOURCE_TYPEFORM,
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => $name,
                'mime' => self::mimeOf($response->header('Content-Type'), $name),
                'size' => strlen($body),
            ]);
            $count++;
        }

        return $count;
    }

    public function upload(Submission $submission, UploadedFile $file, User $by): SubmissionAttachment
    {
        $name = $file->getClientOriginalName() !== '' ? $file->getClientOriginalName() : 'document';
        $path = $file->storeAs(self::directory($submission), Str::random(8).'-'.self::safeName($name), self::DISK);

        return $submission->attachments()->create([
            'source' => SubmissionAttachment::SOURCE_UPLOAD,
            'disk' => self::DISK,
            'path' => (string) $path,
            'original_name' => $name,
            'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size' => $file->getSize() ?: null,
            'uploaded_by' => $by->id,
        ]);
    }

    public function delete(SubmissionAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    /** Remove every stored file of a submission (before the submission row is deleted). */
    public function purge(Submission $submission): void
    {
        Storage::disk(self::DISK)->deleteDirectory(self::directory($submission));
    }

    private static function safeName(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $slug = Str::slug(Str::limit($base, 60, '')) ?: 'file';

        return $ext !== '' ? $slug.'.'.Str::lower($ext) : $slug;
    }

    private static function mimeOf(?string $header, string $name): ?string
    {
        $mime = $header !== null ? Str::lower(trim(explode(';', $header)[0])) : '';
        if ($mime !== '' && $mime !== 'application/octet-stream') {
            return $mime;
        }

        return match (Str::lower(pathinfo($name, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => $mime !== '' ? $mime : null,
        };
    }
}
