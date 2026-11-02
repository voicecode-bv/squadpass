<x-mail::message>
# {{ __('You are now a member of :club', ['club' => $club->name]) }}

{{ __(':club has added you to SquadPass with membership number :number. Here you can book lessons and keep track of your subscription.', ['club' => $club->name, 'number' => $membershipNumber]) }}

@if ($accountCreated)
{{ __('An account has been created for :email. Choose your password via the button below and log in.', ['email' => $email]) }}

<x-mail::button :url="$actionUrl">
{{ __('Choose a password') }}
</x-mail::button>
@else
{{ __('You can log in with your existing account.') }}

<x-mail::button :url="$actionUrl">
{{ __('Log in') }}
</x-mail::button>
@endif

{{ __('If you did not expect this email, you can ignore it or contact :club.', ['club' => $club->name]) }}
</x-mail::message>
