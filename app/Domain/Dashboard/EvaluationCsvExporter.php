<?php

namespace App\Domain\Dashboard;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;

/**
 * Streams the current list as CSV: one row per evaluation, info fields, overall %, and one column per section %
 * when a single form is selected.
 */
class EvaluationCsvExporter
{
    /**
     * @param  Builder<Submission>  $query
     * @return \Generator<int, string>
     */
    public function rows(Builder $query, ?Form $singleForm): \Generator
    {
        $sectionNames = $singleForm?->currentVersion?->sections()->pluck('name')->all() ?? [];

        $header = ['Submitted at', 'Hospital', 'Form', 'Facility', 'Location', 'Visit date', 'Doctor', 'Booking method', 'Branch', 'Overall %', 'RAG'];
        foreach ($sectionNames as $name) {
            $header[] = 'Section: '.$name.' %';
        }
        $header[] = 'Evaluation URL';
        yield $this->line($header);

        $query = $query->with(['sectionScores']);

        foreach ($query->lazyById(200, 'submissions.id') as $submission) {
            /** @var Submission $submission */
            $row = [
                $submission->submitted_at?->toDateTimeString() ?? '',
                $submission->hospital->name,
                $submission->form->title,
                $submission->infoValue('facility') ?? '',
                $submission->infoValue('location') ?? '',
                $submission->infoValue('visit_date') ?? '',
                $submission->infoValue('doctor') ?? '',
                $submission->infoValue('booking_method') ?? '',
                $submission->branch->name ?? ($submission->infoValue('branch') ?? ''),
                $submission->overall_pct === null ? '' : number_format($submission->overall_pct, 2, '.', ''),
                $submission->rag->value ?? '',
            ];
            if ($sectionNames !== []) {
                $byName = $submission->sectionScores->keyBy('section_name');
                foreach ($sectionNames as $name) {
                    $pct = $byName->get($name)?->pct;
                    $row[] = $pct === null ? '' : number_format((float) $pct, 2, '.', '');
                }
            }
            $row[] = route('evaluations.show', $submission);

            yield $this->line($row);
        }
    }

    /**
     * @param  list<string|int|float>  $fields
     */
    private function line(array $fields): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return implode(',', $fields)."\n";
        }
        fputcsv($handle, $fields, ',', '"', '\\', "\n");
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return $line;
    }
}
