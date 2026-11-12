{{--
    Full-screen hero backdrop. resources/js/hero-club.js renders a cross-section
    of a club in 3D across the full width: members walk in one after another,
    are greeted at the front desk, check in at the QR scanner on the desk and
    walk on to the dance studio, the gym, the yoga studio, the padel court and
    the golf room, with the camera following them. The SVG (the check-in at the desk,
    looped by the .sp-club rules in app.css) is the fallback without WebGL;
    with reduced motion only that still SVG shows.

    Rendered in the layout's backdrop slot, behind the header and the hero text.
--}}
<div
    class="absolute inset-x-0 top-0 isolate h-[calc(max(34rem,100svh_-_18rem)_+_6rem)] overflow-hidden lg:h-[calc(max(32rem,100svh_-_19rem)_+_6rem)] bg-linear-to-b from-sky-100 via-blue-50 to-zinc-50 dark:from-blue-950 dark:via-indigo-950 dark:to-zinc-950"
    aria-hidden="true"
    data-hero-club
    data-club="Sportclub Centrum"
    data-studio="{{ __('Dance studio') }}"
    data-gym="{{ __('Gym floor') }}"
    data-yoga="{{ __('Yoga studio') }}"
    data-court="Padelclub"
    data-golf="Golf"
    data-checked-in="{{ __('Checked in') }}"
    data-members="{{ json_encode([
        ['activity' => 'dance', 'greeting' => __('Hi :name!', ['name' => 'Alex']), 'label' => 'Alex · '.__('Dance class')],
        ['activity' => 'gym', 'greeting' => __('Hi :name!', ['name' => 'Sanne']), 'label' => 'Sanne · '.__('Gym floor')],
        ['activity' => 'yoga', 'greeting' => __('Hi :name!', ['name' => 'Mila']), 'label' => 'Mila · Yoga'],
        ['activity' => 'padel', 'greeting' => __('Hi :name!', ['name' => 'Jesse']), 'label' => 'Jesse · Padelclub'],
        ['activity' => 'golf', 'greeting' => __('Hi :name!', ['name' => 'Daan']), 'label' => 'Daan · '.__('Putting green')],
    ]) }}"
>
    {{-- Still fallback: right of the text on wide screens, below it on phones. --}}
    <div class="sp-club-fallback absolute inset-x-6 bottom-10 mx-auto max-w-md overflow-hidden rounded-[2rem] shadow-2xl shadow-blue-950/30 ring-1 ring-white/20 lg:inset-x-auto lg:top-1/2 lg:right-[max(2rem,calc((100vw-72rem)/2+2rem))] lg:bottom-auto lg:w-[30rem] lg:max-w-none lg:-translate-y-1/2">
            <svg viewBox="0 0 480 400" class="sp-club size-full" data-test="hero-club">
                <defs>
                    <linearGradient id="sp-club-wall" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#3b82f6" />
                        <stop offset="1" stop-color="#4338ca" />
                    </linearGradient>
                    <linearGradient id="sp-club-floor" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#c7d2fe" />
                        <stop offset="1" stop-color="#e0e7ff" />
                    </linearGradient>
                    <radialGradient id="sp-club-lamp" cx="0.5" cy="0" r="0.8">
                        <stop offset="0" stop-color="#fef3c7" stop-opacity="0.55" />
                        <stop offset="1" stop-color="#fef3c7" stop-opacity="0" />
                    </radialGradient>
                    <filter id="sp-club-soft" x="-50%" y="-50%" width="200%" height="200%">
                        <feGaussianBlur stdDeviation="5" />
                    </filter>
                </defs>

                {{-- The club: wall, lamps, sign, rack and floor --}}
                <rect width="480" height="292" fill="url(#sp-club-wall)" />
                <circle cx="420" cy="30" r="110" fill="#fff" opacity="0.06" />

                @foreach ([70, 430] as $lampX)
                    <line x1="{{ $lampX }}" y1="0" x2="{{ $lampX }}" y2="38" stroke="#fff" stroke-opacity="0.5" stroke-width="2" />
                    <path d="M{{ $lampX - 22 }} 58 a22 22 0 0 1 44 0 z" fill="#fff" />
                    <path d="M{{ $lampX - 70 }} 58 L{{ $lampX + 70 }} 58 L{{ $lampX + 130 }} 292 L{{ $lampX - 130 }} 292 Z" fill="url(#sp-club-lamp)" opacity="0.35" />
                @endforeach

                <g opacity="0.95">
                    <rect x="160" y="18" width="168" height="44" rx="12" fill="#fff" fill-opacity="0.14" />
                    <circle cx="184" cy="40" r="12" fill="#fff" />
                    <path d="M178 40 l4 4 l8 -9" stroke="#2563eb" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                    <text x="204" y="45" fill="#fff" font-size="14" font-weight="600" font-family="inherit">Sportclub Centrum</text>
                </g>

                <g opacity="0.22" fill="#fff">
                    <rect x="392" y="170" width="6" height="122" rx="3" />
                    <rect x="460" y="170" width="6" height="122" rx="3" />
                    @foreach ([196, 230, 264] as $shelfY)
                        <rect x="388" y="{{ $shelfY }}" width="82" height="5" rx="2.5" />
                        <rect x="404" y="{{ $shelfY - 12 }}" width="12" height="14" rx="4" />
                        <rect x="440" y="{{ $shelfY - 12 }}" width="12" height="14" rx="4" />
                    @endforeach
                </g>

                <rect y="288" width="480" height="112" fill="url(#sp-club-floor)" />
                <rect y="286" width="480" height="6" fill="#1e3a8a" opacity="0.18" />
                @foreach ([-120, 0, 120, 240, 360, 480, 600] as $tileX)
                    <line x1="{{ 240 + ($tileX - 240) * 0.45 }}" y1="292" x2="{{ $tileX }}" y2="400" stroke="#6366f1" stroke-opacity="0.12" stroke-width="2" />
                @endforeach

                {{-- Plant in the corner --}}
                <g>
                    <ellipse cx="38" cy="352" rx="26" ry="6" fill="#1e1b4b" opacity="0.15" />
                    <path d="M22 312 h32 l-5 40 h-22 z" fill="#fff" />
                    <path d="M38 312 C24 290 20 268 28 250 C40 266 42 290 38 312" fill="#34d399" />
                    <path d="M38 312 C44 284 58 266 72 262 C70 286 56 302 38 312" fill="#10b981" />
                    <path d="M38 312 C30 288 6 278 0 280 C6 298 22 310 38 312" fill="#6ee7b7" />
                </g>

                {{-- Staff member behind the desk --}}
                <g class="sp-club-staff">
                    <path d="M118 250 V196 a28 28 0 0 1 28 -28 h8 a28 28 0 0 1 28 28 V250 Z" fill="#1d4ed8" />
                    <path d="M140 170 l10 14 l10 -14" fill="#fff" />
                    <circle cx="166" cy="200" r="5" fill="#fff" opacity="0.85" />
                    <rect x="143" y="150" width="14" height="22" rx="6" fill="#e8b48f" />
                    <circle cx="150" cy="138" r="22" fill="#f1c7a5" />
                    <path d="M128 136 C128 116 142 108 154 110 C168 112 174 124 172 136 C166 126 156 122 146 126 C138 128 132 132 128 136 Z" fill="#7c2d12" />
                    <circle cx="143" cy="140" r="2.2" fill="#1e293b" />
                    <circle cx="157" cy="140" r="2.2" fill="#1e293b" />
                    <path d="M144 149 q6 5 12 0" stroke="#9a3412" stroke-width="2.2" fill="none" stroke-linecap="round" />

                    <g class="sp-club-wave">
                        <rect x="173" y="132" width="15" height="54" rx="7.5" fill="#1d4ed8" />
                        <circle cx="180.5" cy="130" r="9" fill="#f1c7a5" />
                    </g>
                </g>

                {{-- Greeting --}}
                <g class="sp-club-bubble">
                    <rect x="176" y="64" width="104" height="38" rx="14" fill="#fff" />
                    <path d="M186 98 L176 114 L200 101 Z" fill="#fff" />
                    <text x="228" y="88" text-anchor="middle" fill="#1e293b" font-size="15" font-weight="600" font-family="inherit">{{ __('Hi :name!', ['name' => 'Alex']) }}</text>
                </g>

                {{-- Front desk with the monitor (from the back) and the QR scanner --}}
                <ellipse cx="170" cy="352" rx="120" ry="10" fill="#1e1b4b" opacity="0.18" filter="url(#sp-club-soft)" />
                <rect x="96" y="186" width="56" height="38" rx="6" fill="#e2e8f0" />
                <rect x="118" y="222" width="12" height="6" fill="#cbd5e1" />
                <rect x="66" y="238" width="208" height="112" rx="8" fill="#fff" />
                <rect x="66" y="300" width="208" height="12" fill="#2563eb" />
                <circle cx="170" cy="272" r="13" fill="#2563eb" />
                <path d="M164 272 l4 4 l8 -9" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                <rect x="58" y="226" width="224" height="14" rx="6" fill="#f8fafc" />

                <g>
                    <rect x="244" y="204" width="7" height="24" rx="3" fill="#475569" />
                    <rect x="232" y="180" width="36" height="30" rx="7" fill="#0f172a" />
                    <rect x="236" y="184" width="28" height="22" rx="4" fill="#1e3a8a" />
                    <g class="sp-club-scan-ok">
                        <rect x="236" y="184" width="28" height="22" rx="4" fill="#22c55e" />
                        <path d="M243 195 l4 4 l8 -9" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                    </g>
                    <path class="sp-club-beam" d="M268 186 L296 176 L296 216 L268 204 Z" fill="#f87171" />
                </g>

                {{-- Member: walks in, scans the QR code in the app and walks on --}}
                <g transform="translate(22 0)">
                    <g class="sp-club-member">
                        <ellipse cx="330" cy="360" rx="34" ry="7" fill="#1e1b4b" opacity="0.2" />

                        <g class="sp-club-leg-a">
                            <rect x="318" y="284" width="14" height="70" rx="7" fill="#1e293b" />
                            <rect x="312" y="348" width="22" height="10" rx="5" fill="#fff" />
                        </g>
                        <g class="sp-club-leg-b">
                            <rect x="330" y="284" width="14" height="70" rx="7" fill="#334155" />
                            <rect x="324" y="348" width="22" height="10" rx="5" fill="#f8fafc" />
                        </g>

                        <rect x="348" y="212" width="12" height="60" rx="6" fill="#ea580c" />
                        <circle cx="354" cy="274" r="6.5" fill="#a86b4a" />
                        <rect x="306" y="198" width="50" height="94" rx="20" fill="#f97316" />
                        <path d="M316 262 h30" stroke="#ea580c" stroke-width="3" stroke-linecap="round" />
                        <rect x="323" y="182" width="14" height="20" rx="6" fill="#a86b4a" />
                        <circle cx="330" cy="172" r="22" fill="#c68863" />
                        <path d="M334 150 C350 150 356 164 352 180 C346 172 340 168 334 168 C326 168 318 164 312 160 C316 152 324 149 334 150 Z" fill="#111827" />
                        <circle cx="318" cy="174" r="2.2" fill="#1e293b" />
                        <path d="M312 184 q5 4 10 1" stroke="#7c2d12" stroke-width="2.2" fill="none" stroke-linecap="round" />

                        <g class="sp-club-arm">
                            <rect x="308" y="206" width="13" height="58" rx="6.5" fill="#fb923c" />
                            <circle cx="314.5" cy="266" r="6.5" fill="#c68863" />
                            <rect x="304" y="258" width="20" height="32" rx="4" fill="#0f172a" />
                            <rect x="307" y="262" width="14" height="24" rx="2" fill="#fff" />
                            <rect x="309" y="265" width="4" height="4" fill="#1e3a8a" />
                            <rect x="315" y="265" width="4" height="4" fill="#1e3a8a" />
                            <rect x="309" y="271" width="4" height="4" fill="#1e3a8a" />
                            <rect x="315" y="277" width="4" height="4" fill="#1e3a8a" />
                            <rect x="312" y="273" width="2" height="2" fill="#1e3a8a" />
                        </g>
                    </g>
                </g>

                {{-- The check-in confirmation --}}
                <g class="sp-club-ok">
                    <rect x="262" y="118" width="150" height="48" rx="14" fill="#fff" />
                    <circle cx="286" cy="142" r="13" fill="#22c55e" />
                    <path d="M280 142 l4 4 l8 -9" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                    <text x="306" y="138" fill="#0f172a" font-size="13" font-weight="600" font-family="inherit">{{ __('Checked in') }}</text>
                    <text x="306" y="155" fill="#64748b" font-size="11" font-family="inherit">Alex Janssen · 18:24</text>
                </g>
            </svg>
    </div>

    {{-- Keep the text readable: fade the scene out towards the text at the top and into the page below. --}}
    <div class="pointer-events-none absolute inset-0 z-10 bg-linear-to-b from-zinc-50/95 via-zinc-50/40 via-35% to-transparent to-55% dark:from-zinc-950/95 dark:via-zinc-950/40"></div>
    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-10 h-12 bg-linear-to-b from-transparent to-zinc-50 dark:to-zinc-950"></div>
</div>
