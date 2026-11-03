@props(['club' => null])
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ $club?->name ?? config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            @if ($club)
                {{ __('Sent by :club via :app.', ['club' => $club->name, 'app' => config('app.name')]) }}
            @else
                © {{ date('Y') }} {{ config('app.name') }}
            @endif
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
