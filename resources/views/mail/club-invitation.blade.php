<x-mail::message>
# {{ $role->invitedTitle($club->name) }}

{{ __('Accept the invitation via the button below. If you do not have an account yet, you can create one first — the invitation stays linked.') }}

<x-mail::button :url="$acceptUrl">
{{ __('Accept invitation') }}
</x-mail::button>

{{ __('This invitation is valid until :date.', ['date' => $expiresAt->timezone($club->timezone)->isoFormat('D MMMM YYYY')]) }}

{{ __('If you were not expecting this invitation, you can ignore this email.') }}
</x-mail::message>
