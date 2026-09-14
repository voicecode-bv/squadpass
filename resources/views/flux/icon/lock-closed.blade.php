{{-- Credit: Fluent UI System Icons (https://github.com/microsoft/fluentui-system-icons) --}}

@props([
    'variant' => 'outline',
])

@php
$classes = Flux::classes('shrink-0')
    ->add(match ($variant) {
        'outline' => '[:where(&)]:size-6',
        'solid' => '[:where(&)]:size-6',
        'mini' => '[:where(&)]:size-5',
        'micro' => '[:where(&)]:size-4',
    });

$icon = match ($variant) {
    'outline' => 'fluentui-lock-closed-24-o',
    'solid' => 'fluentui-lock-closed-24',
    'mini' => 'fluentui-lock-closed-20-o',
    'micro' => 'fluentui-lock-closed-16-o',
};
@endphp

{{ svg($icon, '', $attributes->class($classes)->getAttributes() + ['data-flux-icon' => true, 'data-slot' => 'icon', 'aria-hidden' => 'true']) }}
