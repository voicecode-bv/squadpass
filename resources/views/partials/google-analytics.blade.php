{{--
    Google Analytics with Consent Mode v2, only on production so local and test traffic stays out of the statistics.
    Every consent type starts as denied: analytics cookies are only set after the visitor accepts in the cookie banner.
    The choice lives in a first-party functional cookie for a year; anything with [data-cookie-settings] reopens the banner.
--}}
@production
    @if (filled($measurementId = config('services.google_analytics.measurement_id')))
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}

            (function () {
                var cookieName = 'sp_cookie_consent';
                var match = document.cookie.match(new RegExp('(?:^|; )' + cookieName + '=(granted|denied)'));
                var choice = match ? match[1] : null;

                gtag('consent', 'default', {
                    ad_storage: 'denied',
                    ad_user_data: 'denied',
                    ad_personalization: 'denied',
                    analytics_storage: choice === 'granted' ? 'granted' : 'denied',
                    wait_for_update: 500,
                });

                function store(value) {
                    choice = value;
                    document.cookie = cookieName + '=' + value + '; max-age=31536000; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
                    gtag('consent', 'update', { analytics_storage: value });
                    hide();
                }

                function hide() {
                    var banner = document.getElementById('cookie-banner');

                    if (banner) {
                        banner.remove();
                    }
                }

                function show() {
                    var template = document.getElementById('cookie-banner-template');

                    if (! template || document.getElementById('cookie-banner')) {
                        return;
                    }

                    document.body.appendChild(template.content.cloneNode(true));
                    document.getElementById('cookie-banner').addEventListener('click', function (event) {
                        var button = event.target.closest('[data-cookie-choice]');

                        if (button) {
                            store(button.getAttribute('data-cookie-choice'));
                        }
                    });
                }

                function showWhenUndecided() {
                    if (choice === null) {
                        show();
                    }
                }

                document.addEventListener('click', function (event) {
                    if (event.target.closest('[data-cookie-settings]')) {
                        event.preventDefault();
                        show();
                    }
                });

                document.addEventListener('DOMContentLoaded', showWhenUndecided);
                {{-- wire:navigate swaps the body, which takes the banner with it. --}}
                document.addEventListener('livewire:navigated', showWhenUndecided);
            })();

            gtag('js', new Date());
            gtag('config', @js($measurementId));
        </script>
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}"></script>

        <template id="cookie-banner-template">
            <div id="cookie-banner" role="dialog" aria-live="polite" aria-labelledby="cookie-banner-title" class="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-xl rounded-2xl bg-white p-5 text-zinc-900 shadow-lg ring-1 ring-zinc-200 sm:inset-x-6 dark:bg-zinc-900 dark:text-zinc-100 dark:ring-zinc-700">
                <p id="cookie-banner-title" class="font-semibold">{{ __('We use cookies') }}</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('With your permission we use analytics cookies from Google Analytics to see how SquadPass is used, so we can improve it. You can change your choice at any time via Cookie settings.') }}
                    <a href="{{ route('privacy') }}" class="font-medium text-blue-600 underline hover:text-blue-700 dark:text-blue-400">{{ __('Privacy statement') }}</a>
                </p>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" data-cookie-choice="denied" class="rounded-lg bg-zinc-100 px-4 py-2 text-sm font-medium text-zinc-900 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:bg-zinc-700">
                        {{ __('Reject') }}
                    </button>
                    <button type="button" data-cookie-choice="granted" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        {{ __('Accept') }}
                    </button>
                </div>
            </div>
        </template>
    @endif
@endproduction
