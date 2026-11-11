<?php

namespace App\Http\Controllers;

use App\Livewire\Hospital\Evaluations\Show;
use App\Models\FormField;
use App\Models\Submission;
use App\Models\SubmissionAttachment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Visit report PDF: the same content as the evaluation page in a print layout, with stored attachments embedded.
 */
class EvaluationPdfController extends Controller
{
    /** Images inlined into one PDF: total bytes and count, so dompdf stays within shared-hosting memory. */
    public const MAX_INLINE_TOTAL_BYTES = 10 * 1024 * 1024;

    public const MAX_INLINE_IMAGES = 12;

    public function __invoke(Submission $evaluation): Response
    {
        Gate::authorize('view', $evaluation);

        $pdf = Pdf::loadHTML($this->html($evaluation))->setPaper('a4');

        $name = 'evaluation-'.($evaluation->submitted_at?->format('Ymd') ?? 'partial').'-'.substr($evaluation->uuid, 0, 8).'.pdf';

        return $pdf->download($name);
    }

    /** Render the report HTML (also used by tests and previews). */
    public function html(Submission $evaluation): string
    {
        $evaluation->load(['form.hospital', 'formVersion', 'branch', 'sectionScores', 'answers', 'attachments.uploader', 'reviewer']);

        $fieldsBySection = $evaluation->formVersion->fields()->get()->groupBy('section_id');
        $answersByField = $evaluation->answers->keyBy('typeform_field_id');
        $scores = $evaluation->sectionScores->keyBy('section_id');
        $typeformFiles = $evaluation->attachments->where('source', SubmissionAttachment::SOURCE_TYPEFORM)->keyBy('answer_typeform_field_id');
        $inlineImages = $this->inlineImages($evaluation->attachments);

        $sections = $evaluation->formVersion->sections()->get()->map(function ($section) use ($fieldsBySection, $answersByField, $scores, $typeformFiles) {
            $questions = [];
            $comments = [];
            foreach ($fieldsBySection->get($section->id, collect())->sortBy('display_order') as $field) {
                /** @var FormField $field */
                $answer = $answersByField->get($field->typeform_field_id);
                if ($answer === null) {
                    continue;
                }
                $row = ['field' => $field, 'answer' => $answer, 'file' => $typeformFiles->get($field->typeform_field_id)];
                if (Show::isCommentField($field)) {
                    $comments[] = $row;
                } else {
                    $questions[] = $row;
                }
            }

            return ['section' => $section, 'score' => $scores->get($section->id), 'rows' => $questions, 'comments' => $comments];
        });

        $scorable = $evaluation->formVersion->fields()->reorder()->where('is_scored', true)->distinct()->pluck('section_id')->map(fn ($id) => (int) $id)->all();
        $journey = $evaluation->sectionScores->filter(fn ($s) => in_array((int) $s->section_id, $scorable, true))->values();

        $logo = null;
        $logoPath = $evaluation->form->hospital->logo_path;
        if ($logoPath !== null && Storage::disk('public')->exists($logoPath)) {
            $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', default => null
            };
            if ($mime !== null) {
                $logo = 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($logoPath));
            }
        }

        return view('pdf.evaluation', [
            'e' => $evaluation,
            'sections' => $sections,
            'journey' => $journey,
            'documents' => $evaluation->attachments->where('source', SubmissionAttachment::SOURCE_UPLOAD)->values(),
            'inlineImages' => $inlineImages,
            'logo' => $logo,
        ])->render();
    }

    /**
     * Data URIs for the images that fit the budget, keyed by attachment id; each file is read once.
     *
     * @param  Collection<int, SubmissionAttachment>  $attachments
     * @return array<int, string>
     */
    private function inlineImages($attachments): array
    {
        $uris = [];
        $bytes = 0;
        foreach ($attachments as $attachment) {
            if (! $attachment->isImage() || count($uris) >= self::MAX_INLINE_IMAGES) {
                continue;
            }
            $size = (int) ($attachment->size ?? 0);
            if ($size > SubmissionAttachment::MAX_INLINE_BYTES || $bytes + $size > self::MAX_INLINE_TOTAL_BYTES) {
                continue;
            }
            $uri = $attachment->dataUri();
            if ($uri === null) {
                continue;
            }
            $uris[$attachment->id] = $uri;
            $bytes += $size;
        }

        return $uris;
    }
}
