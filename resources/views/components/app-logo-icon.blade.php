{{--
    The SquadPass mark: three stacked passes with a knocked-out "S". The shape is
    drawn as a mask over a currentColor fill, so the mark takes the text colour of
    its context (white on the brand blue, ink on a light surface) and the "S" stays
    transparent. Source file: public/images/logos/squadpass-mark.svg.
--}}
@php($maskId = uniqid('sp-mark-'))

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160" {{ $attributes }}>
    <mask id="{{ $maskId }}">
        <g transform="rotate(-14 80 80)">
            <rect x="22" y="24" width="116" height="80" rx="14" fill="#fff" opacity="0.18" />
            <rect x="22" y="36" width="116" height="80" rx="14" fill="#fff" opacity="0.4" />
            <rect x="22" y="48" width="116" height="80" rx="14" fill="#fff" />
            <text x="80" y="110" text-anchor="middle" font-family="Lexend, Helvetica, Arial, sans-serif" font-weight="800" font-size="56" fill="#000">S</text>
        </g>
    </mask>

    <rect width="160" height="160" fill="currentColor" mask="url(#{{ $maskId }})" />
</svg>
