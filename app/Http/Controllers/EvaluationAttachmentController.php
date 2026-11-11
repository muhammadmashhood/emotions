<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\SubmissionAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a stored attachment (Typeform copy or staff upload) from the private disk.
 */
class EvaluationAttachmentController extends Controller
{
    public function __invoke(Submission $evaluation, int $attachment): Response
    {
        Gate::authorize('view', $evaluation);

        $file = SubmissionAttachment::query()->where('submission_id', $evaluation->id)->findOrFail($attachment);

        return self::stream($file);
    }

    public static function stream(SubmissionAttachment $file): Response
    {
        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404);

        $disposition = $file->isImage() || $file->mime === 'application/pdf' ? 'inline' : 'attachment';

        return response($disk->get($file->path), 200, [
            'Content-Type' => $file->mime ?? 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($file->original_name).'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
