<x-mail::message :club="$club">
# {{ __('Use your credits before they expire') }}

{{ trans_choice('{1} You have :count unused lesson credit at :club that expires on :date.|[2,*] You have :count unused lesson credits at :club that expire on :date.', $credits, ['club' => $club->name, 'date' => $expiresAt->translatedFormat('l j F H:i')]) }}

{{ __('Book a lesson now so they do not go to waste.') }}

<x-mail::button :url="$actionUrl">
{{ __('Book a lesson') }}
</x-mail::button>
</x-mail::message>
