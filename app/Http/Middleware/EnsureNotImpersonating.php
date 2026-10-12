<?php

namespace App\Http\Middleware;

use App\Services\Impersonation\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks credential and account changes while a staff member is impersonating,
 * so impersonation can never be turned into permanent access to the account.
 */
class EnsureNotImpersonating
{
    /**
     * Route names that may not be reached while impersonating.
     *
     * @var array<int, string>
     */
    private const BLOCKED_ROUTES = [
        'profile.edit',
        'security.edit',
        'password.confirm',
        'password.confirm.store',
        'two-factor.*',
        'passkey.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            Impersonation::isActive() && $request->routeIs(...self::BLOCKED_ROUTES),
            403,
            __('This is not available while you are viewing the app as a member.'),
        );

        return $next($request);
    }
}
