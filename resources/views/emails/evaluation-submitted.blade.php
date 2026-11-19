<x-mail::message>
# New evaluation received

A mystery-shopper evaluation has just come in.

@include('emails.partials.evaluation-summary', ['submission' => $submission])

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
