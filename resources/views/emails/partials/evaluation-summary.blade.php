@php
    $facility = $submission->branch?->name ?? $submission->infoValue('facility');
    $visitDate = $submission->infoValue('visit_date') ?? $submission->submitted_at?->toFormattedDateString();
@endphp
**Hospital:** {{ $submission->hospital->name }}<br>
**Survey:** {{ $submission->form->title }}<br>
**Facility:** {{ $facility ?: 'Not given' }}<br>
**Visit date:** {{ $visitDate ?: 'Not given' }}<br>
**Score:** {{ $submission->overall_pct === null ? 'Not scored' : number_format((float) $submission->overall_pct, 1).'%' }}

<x-mail::button :url="route('evaluations.show', $submission)">
View evaluation
</x-mail::button>
