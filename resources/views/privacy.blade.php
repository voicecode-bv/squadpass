@php
    $appName = config('app.name');
    $email = config('mail.from.address');

    $sections = [
        [
            'heading' => __('Who we are'),
            'paragraphs' => [
                __(':app is the platform that sports clubs, studios and gyms use for member management, lessons, payments and check-ins.', ['app' => $appName]),
                __('Do you have a question about privacy or do you want to exercise one of your rights? Email us at :email.', ['email' => $email]),
            ],
        ],
        [
            'heading' => __('Your club or us: who is responsible'),
            'paragraphs' => [
                __('When you visit our website or create a club account, we decide what happens with your data. For that data we are the controller.'),
                __('The member data a club manages in :app, such as members, lessons, check-ins and payments, belongs to that club. For that data the club is the controller and we process it on its behalf as processor, under a data processing agreement. Questions about your membership data are best asked to your club.', ['app' => $appName]),
            ],
        ],
        [
            'heading' => __('Which data we process'),
            'items' => [
                __('Account details: name, email address and password (stored as a secure hash, never readable).'),
                __('Membership details a club records: membership number, phone number, date of birth, membership status, subscriptions and strip cards.'),
                __('Usage within the club: lessons booked, check-ins, forms filled in and signed, and support tickets.'),
                __('Payment details: payments and direct debit mandates, processed via Mollie.'),
                __('Technical data: IP address and browser in your session and in log files, to keep the platform secure and working.'),
                __('Analytics data: only with your permission, via Google Analytics.'),
            ],
        ],
        [
            'heading' => __('Why we process data'),
            'items' => [
                __('To provide the service you or your club signed up for, such as your account, lessons and payments (performance of a contract).'),
                __('To keep our financial records, which the law requires (legal obligation).'),
                __('To secure the platform and prevent abuse, for example with a bot check on signup forms (legitimate interest).'),
                __('To understand how our website is used, so we can improve it (consent).'),
            ],
        ],
        [
            'heading' => __('Who we share data with'),
            'paragraphs' => [
                __('We never sell your data. We only share data with parties that help us run :app, and only as far as they need it:', ['app' => $appName]),
            ],
            'items' => [
                __('Laravel Cloud, for hosting the platform and storing files.'),
                __('Postmark, for sending emails such as invitations and password resets.'),
                __('Mollie, for payments and direct debits.'),
                __('Moneybird, for invoicing, only when a club links its own administration.'),
                __('Cloudflare Turnstile, for the bot check on public signup forms.'),
                __('Google Analytics, for website statistics, only with your permission.'),
            ],
            'after' => [
                __('Some of these parties are based outside the European Union. In that case we rely on the EU-US Data Privacy Framework or the European Commission\'s standard contractual clauses.'),
            ],
        ],
        [
            'heading' => __('Cookies'),
            'paragraphs' => [
                __('We use functional cookies that are needed to log in and to secure forms. These do not require permission.'),
                __('With your permission we place analytics cookies from Google Analytics. Without permission, no analytics cookies are placed. You can change your choice at any time.'),
            ],
            'cookieSettings' => true,
        ],
        [
            'heading' => __('How long we keep data'),
            'paragraphs' => [
                __('We keep data as long as an account or membership is active. When a club or account is deleted, we remove the personal data, except what the law requires us to keep, such as financial records, which we keep for seven years.'),
            ],
        ],
        [
            'heading' => __('How we secure data'),
            'paragraphs' => [
                __('All traffic runs over encrypted connections (HTTPS), passwords are stored as secure hashes and you can protect your account with two-factor authentication or a passkey. Within :app every club only sees its own data.', ['app' => $appName]),
            ],
        ],
        [
            'heading' => __('Your rights'),
            'paragraphs' => [
                __('You have the right to access, correct and delete your personal data, to restrict or object to its processing and to receive your data in a portable format. You can withdraw consent you gave at any time.'),
                __('Email us at :email and we will respond within one month. For membership data we may refer you to your club. Do you think we handle your data incorrectly? You can file a complaint with the Dutch Data Protection Authority (Autoriteit Persoonsgegevens).', ['email' => $email]),
            ],
        ],
        [
            'heading' => __('Changes'),
            'paragraphs' => [
                __('We may update this privacy statement when our service changes. The date at the top shows when it was last updated.'),
            ],
        ],
    ];
@endphp

<x-marketing.layout :title="__('Privacy statement').' | '.$appName" :description="__('How :app handles personal data: which data we process, why, who we share it with and what rights you have.', ['app' => $appName])" :canonical="route('privacy')">
    <article class="mx-auto max-w-3xl py-16 lg:py-24">
        <h1 class="text-4xl font-bold tracking-tight text-white sm:text-5xl">{{ __('Privacy statement') }}</h1>
        <p class="mt-4 text-sm text-zinc-500">{{ __('Last updated: :date', ['date' => \Illuminate\Support\Carbon::parse('2026-11-04')->translatedFormat('j F Y')]) }}</p>
        <p class="mt-6 text-lg leading-relaxed text-zinc-400">
            {{ __('This privacy statement explains which personal data :app processes, why we do so and what rights you have.', ['app' => $appName]) }}
        </p>

        @foreach ($sections as $section)
            <section class="mt-12">
                <h2 class="text-2xl font-semibold tracking-tight text-white">{{ $section['heading'] }}</h2>

                @foreach ($section['paragraphs'] ?? [] as $paragraph)
                    <p class="mt-4 leading-relaxed text-zinc-400">{{ $paragraph }}</p>
                @endforeach

                @if (isset($section['items']))
                    <ul class="mt-4 flex list-disc flex-col gap-2 ps-5 leading-relaxed text-zinc-400">
                        @foreach ($section['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif

                @foreach ($section['after'] ?? [] as $paragraph)
                    <p class="mt-4 leading-relaxed text-zinc-400">{{ $paragraph }}</p>
                @endforeach

                @if ($section['cookieSettings'] ?? false)
                    @production
                        <button type="button" data-cookie-settings class="mt-6 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                            {{ __('Cookie settings') }}
                        </button>
                    @endproduction
                @endif
            </section>
        @endforeach
    </article>
</x-marketing.layout>
