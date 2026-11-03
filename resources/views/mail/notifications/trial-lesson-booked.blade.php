<x-mail::message :club="$club">
# {{ __('See you at :club!', ['club' => $club->name]) }}

{{ __('Your trial lesson :lesson is booked for :date at :time.', ['lesson' => $lesson, 'date' => $startsAt->translatedFormat('l j F'), 'time' => $startsAt->format('H:i')]) }}

@if ($accountCreated)
{{ __('We created an account for you. Choose a password via the button below to see your booking.') }}

<x-mail::button :url="$actionUrl">
{{ __('Choose a password') }}
</x-mail::button>
@else
<x-mail::button :url="$actionUrl">
{{ __('Log in') }}
</x-mail::button>
@endif

{{ __('Can you not make it after all? Let :club know, so someone else can take your spot.', ['club' => $club->name]) }}
</x-mail::message>
