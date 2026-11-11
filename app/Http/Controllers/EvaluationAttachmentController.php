<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\SubmissionAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;
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

        return self::fileResponse((string) $disk->get($file->path), $file->mime, $file->original_name);
    }

    /**
     * Shared by the stored-copy and on-demand routes: inline only raster images and PDFs, never SVG or HTML-like
     * types (they would run script on our origin), and sandbox whatever the browser renders.
     */
    public static function fileResponse(string $body, ?string $mime, string $filename): Response
    {
        $mime = strtolower(trim((string) explode(';', (string) $mime)[0]));
        $inlineSafe = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'], true);
        if (! $inlineSafe) {
            $mime = 'application/octet-stream';
        }

        return response($body, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($inlineSafe ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT, $filename, self::asciiFallback($filename)),
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private static function asciiFallback(string $filename): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?? 'file';
        $ascii = str_replace(['"', '\\', '/', '%'], '_', $ascii);

        return $ascii !== '' ? $ascii : 'file';
    }
}
