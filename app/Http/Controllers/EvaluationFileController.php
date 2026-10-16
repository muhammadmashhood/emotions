<?php

namespace App\Http\Controllers;

use App\Domain\Typeform\Exceptions\TypeformException;
use App\Domain\Typeform\TypeformClient;
use App\Models\Answer;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams an uploaded file from Typeform using the connection token. The raw Typeform URL never reaches the browser.
 */
class EvaluationFileController extends Controller
{
    public function __invoke(Request $request, Submission $evaluation, int $answer): Response
    {
        Gate::authorize('view', $evaluation);

        $answerModel = Answer::query()->where('submission_id', $evaluation->id)->findOrFail($answer);
        $url = $answerModel->fileUrl();
        abort_if($url === null, 404);

        $evaluation->loadMissing('form.connection');

        try {
            $upstream = TypeformClient::for($evaluation->form->connection)->download($url);
        } catch (TypeformException $e) {
            abort(502, 'The file could not be fetched from Typeform.');
        }

        $contentType = $upstream->header('Content-Type') ?: 'application/octet-stream';
        $filename = $answerModel->value_text ?: 'file';
        $disposition = str_starts_with($contentType, 'image/') ? 'inline' : 'attachment';

        return response($upstream->body(), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition.'; filename="'.addslashes($filename).'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
