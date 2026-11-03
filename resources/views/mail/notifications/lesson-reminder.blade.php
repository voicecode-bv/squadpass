<x-mail::message :club="$club">
# {{ __(':lesson starts soon', ['lesson' => $lesson]) }}

{{ __('A reminder that you are booked for :lesson at :club on :date at :time.', ['lesson' => $lesson, 'club' => $club->name, 'date' => $startsAt->translatedFormat('l j F'), 'time' => $startsAt->format('H:i')]) }}

@if ($cancelUntil->isFuture())
{{ __('Can you not make it? Cancel before :moment and you get your credit back.', ['moment' => $cancelUntil->translatedFormat('l j F H:i')]) }}
@else
{{ __('Can you not make it? Please cancel anyway, so someone else can take your spot.') }}
@endif

<x-mail::button :url="$actionUrl">
{{ __('View my lessons') }}
</x-mail::button>

{{ __('You can turn off lesson reminders in your profile settings.') }}
</x-mail::message>
