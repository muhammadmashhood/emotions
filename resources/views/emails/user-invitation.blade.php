<x-mail::message>
# You're invited

You have been invited to join **{{ config('app.name') }}**@if($invitation->hospital) for **{{ $invitation->hospital->name }}**@endif as {{ $invitation->role->label() }}.

<x-mail::button :url="$acceptUrl">
Accept invitation
</x-mail::button>

This link expires {{ $invitation->expires_at->diffForHumans() }}. If you were not expecting this, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
