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
    'outline' => 'fluentui-phone-24-o',
    'solid' => 'fluentui-phone-24',
    'mini' => 'fluentui-phone-20-o',
    'micro' => 'fluentui-phone-16-o',
};
@endphp

{{ svg($icon, '', $attributes->class($classes)->getAttributes() + ['data-flux-icon' => true, 'data-slot' => 'icon', 'aria-hidden' => 'true']) }}
