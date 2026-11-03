<x-mail::message :club="$club">
# {{ __('New trial lesson booked') }}

{{ __(':name booked a trial lesson for :lesson on :date at :time.', ['name' => $name, 'lesson' => $lesson, 'date' => $startsAt->translatedFormat('l j F'), 'time' => $startsAt->format('H:i')]) }}

- {{ __('Email') }}: {{ $email }}
@if ($phone)
- {{ __('Phone') }}: {{ $phone }}
@endif

{{ __('After the lesson you can convert them to a member in the member list.') }}

<x-mail::button :url="$actionUrl">
{{ __('View trial member') }}
</x-mail::button>
</x-mail::message>
