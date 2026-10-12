<?php

namespace App\Services\Impersonation;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Session-backed impersonation state: club staff temporarily browsing the app
 * as one of their members.
 */
class Impersonation
{
    /**
     * Session key holding the id of the user who started the impersonation.
     */
    public const SESSION_KEY = 'impersonator_id';

    /**
     * Session key holding the URL to return to once impersonation stops.
     */
    public const RETURN_KEY = 'impersonation_return_to';

    /**
     * Take over the target user's session on behalf of the current user.
     */
    public static function start(User $target, string $returnTo): void
    {
        $impersonatorId = Auth::id();

        // Auth::login() migrates the session id, so the markers are written afterwards.
        Auth::login($target);

        Session::put(self::SESSION_KEY, $impersonatorId);
        Session::put(self::RETURN_KEY, $returnTo);
    }

    /**
     * Hand the session back to the impersonator. Returns the URL to redirect to.
     */
    public static function stop(): ?string
    {
        $impersonatorId = Session::pull(self::SESSION_KEY);
        $returnTo = Session::pull(self::RETURN_KEY);

        if (! is_int($impersonatorId)) {
            return null;
        }

        $impersonator = User::find($impersonatorId);

        if ($impersonator === null) {
            Auth::logout();

            return null;
        }

        Auth::login($impersonator);

        return is_string($returnTo) ? $returnTo : null;
    }

    public static function isActive(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    /**
     * The user who started the impersonation, or null when not impersonating.
     */
    public static function impersonator(): ?User
    {
        $impersonatorId = Session::get(self::SESSION_KEY);

        return is_int($impersonatorId) ? User::find($impersonatorId) : null;
    }
}
