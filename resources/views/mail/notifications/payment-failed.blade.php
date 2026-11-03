<x-mail::message :club="$club">
# {{ __('Your payment did not go through') }}

{{ __('We could not collect :amount for your subscription at :club. This can happen when there was not enough balance or the bank refused the direct debit.', ['amount' => $amount, 'club' => $club->name]) }}

{{ __('Check your subscription and contact :club if you have questions about this payment.', ['club' => $club->name]) }}

<x-mail::button :url="$actionUrl">
{{ __('View my subscription') }}
</x-mail::button>
</x-mail::message>
