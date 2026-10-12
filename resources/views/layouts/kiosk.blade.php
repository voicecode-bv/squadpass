{{-- Full-screen layout for the check-in kiosk: no sidebar, no navigation, just the QR code on the club's screen. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')

        {{-- The kiosk is the only screen that listens for check-in broadcasts.
             Echo authorizes the private channel over /broadcasting/auth, which
             needs this token — the rest of the app rides on Livewire's own. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @vite('resources/js/echo.js')
    </head>
    <body class="bg-canvas min-h-screen antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center p-6">
            {{ $slot }}
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
