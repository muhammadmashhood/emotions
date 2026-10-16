<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * PDF of an evaluation (milestone 7): same content as the detail page, print layout.
 */
class EvaluationPdfController extends Controller
{
    public function __invoke(Submission $evaluation): Response
    {
        Gate::authorize('view', $evaluation);

        $evaluation->load(['form.hospital', 'formVersion', 'branch', 'sectionScores', 'answers']);

        $fieldsBySection = $evaluation->formVersion->fields()->get()->groupBy('section_id');
        $answersByField = $evaluation->answers->keyBy('typeform_field_id');
        $scores = $evaluation->sectionScores->keyBy('section_id');

        $sections = $evaluation->formVersion->sections()->get()->map(function ($section) use ($fieldsBySection, $answersByField, $scores) {
            $rows = [];
            foreach ($fieldsBySection->get($section->id, collect())->sortBy('display_order') as $field) {
                $answer = $answersByField->get($field->typeform_field_id);
                if ($answer !== null) {
                    $rows[] = ['field' => $field, 'answer' => $answer];
                }
            }

            return ['section' => $section, 'score' => $scores->get($section->id), 'rows' => $rows];
        });

        $pdf = Pdf::loadView('pdf.evaluation', ['e' => $evaluation, 'sections' => $sections])->setPaper('a4');

        $name = 'evaluation-'.($evaluation->submitted_at?->format('Ymd') ?? 'partial').'-'.substr($evaluation->uuid, 0, 8).'.pdf';

        return $pdf->download($name);
    }
}
