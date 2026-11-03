<x-mail::message :club="$club">
# {{ __('Please fill in :form', ['form' => $formName]) }}

{{ __(':club has asked you to fill in the form :form. It only takes a moment.', ['club' => $club->name, 'form' => $formName]) }}

<x-mail::button :url="$actionUrl">
{{ __('Fill in the form') }}
</x-mail::button>
</x-mail::message>
