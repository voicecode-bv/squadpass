<x-mail::message :club="$club">
# {{ $title }}

{{ $excerpt }}

<x-mail::button :url="$actionUrl">
{{ __('Read in the app') }}
</x-mail::button>

{{ __('You receive this email because you are a member of :club.', ['club' => $club->name]) }}
</x-mail::message>
