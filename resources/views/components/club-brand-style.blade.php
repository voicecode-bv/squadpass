{{--
    Repaints SquadPass blue in a club's brand colour by overriding Tailwind's
    --color-blue-* variables. Kept in <body>, not <head>: wire:navigate swaps the
    body but leaves old head styles behind, which would keep a previous club's
    colour when switching clubs. Pass the club, or set resolve to let
    ClubBranding pick it from the URL or the signed-in user.
--}}
@props([
    'club' => null,
    'resolve' => false,
])

@php($brandCss = \App\Services\ClubBranding::css($resolve ? \App\Services\ClubBranding::clubFor($club, auth()->user()) : $club))

@if ($brandCss !== '')
    <style data-club-brand>{!! $brandCss !!}</style>
@endif
