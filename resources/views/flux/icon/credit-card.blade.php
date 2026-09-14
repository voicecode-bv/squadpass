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
    'outline' => 'fluentui-payment-24-o',
    'solid' => 'fluentui-payment-24',
    'mini' => 'fluentui-payment-20-o',
    'micro' => 'fluentui-payment-16-o',
};
@endphp

{{ svg($icon, '', $attributes->class($classes)->getAttributes() + ['data-flux-icon' => true, 'data-slot' => 'icon', 'aria-hidden' => 'true']) }}
