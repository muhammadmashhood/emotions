<x-mail::message>
# New evaluation on your dashboard

An evaluation for **{{ $submission->hospital->name }}** has been approved and is now visible on your dashboard.

@include('emails.partials.evaluation-summary', ['submission' => $submission])

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
