<?php

namespace App\Services;

/**
 * Google Analytics events that happen on the server, right before a redirect.
 * They wait in the session until the next full page renders the analytics
 * partial, which sends them along with the page view.
 */
class AnalyticsEvents
{
    public const string SESSION_KEY = 'analytics.events';

    public const string SIGN_UP = 'sign_up';

    public const string DEMO_START = 'demo_start';

    /**
     * @param  array<string, scalar>  $parameters
     */
    public function queue(string $name, array $parameters = []): void
    {
        session()->push(self::SESSION_KEY, ['name' => $name, 'parameters' => $parameters]);
    }

    /**
     * The queued events, removed from the session so each one is sent once.
     *
     * @return list<array{name: string, parameters: array<string, scalar>}>
     */
    public function pull(): array
    {
        return session()->pull(self::SESSION_KEY, []);
    }
}
