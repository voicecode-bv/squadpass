<x-mail::message>
@if ($role === \App\Enums\ClubInvitationRole::Trainer)
# {{ __('You have been invited as a trainer at :club', ['club' => $club->name]) }}
@else
# {{ __('You have been invited as a member of :club', ['club' => $club->name]) }}
@endif

{{ __('Accept the invitation via the button below. If you do not have an account yet, you can create one first — the invitation stays linked.') }}

<x-mail::button :url="$acceptUrl">
{{ __('Accept invitation') }}
</x-mail::button>

{{ __('This invitation is valid until :date.', ['date' => $expiresAt->timezone($club->timezone)->isoFormat('D MMMM YYYY')]) }}

{{ __('If you were not expecting this invitation, you can ignore this email.') }}
</x-mail::message>
