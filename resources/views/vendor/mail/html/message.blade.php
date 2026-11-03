{{--
    Every SquadPass email. Pass the club with <x-mail::message :club="$club">
    so its email logo heads the message; without a club (account emails such as
    password resets) the SquadPass mark is shown instead.
--}}
@props(['club' => null])
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" :club="$club" />
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer :club="$club" />
</x-slot:footer>
</x-mail::layout>
