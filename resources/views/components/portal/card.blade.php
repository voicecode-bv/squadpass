@props(['delay' => 0])

<div style="--d: {{ $delay }}ms"
     {{ $attributes->class('stagger animate-rise sp-panel group relative overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_8px_24px_-12px_rgba(16,24,40,0.25)]') }}>
    {{ $slot }}
</div>
