<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Membership;
use App\Models\User;
use App\Services\Impersonation\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ImpersonationController extends Controller
{
    /**
     * Start browsing the app as one of the club's members.
     */
    public function store(Request $request, Club $club, Membership $membership): RedirectResponse
    {
        $membership->setRelation('club', $club);

        Gate::authorize('impersonate', $membership);

        $target = $membership->user;

        // Never nest impersonations, impersonate yourself, or step up to an
        // account that outranks the member being viewed.
        abort_if(Impersonation::isActive(), 403);
        abort_if($target->is($request->user()), 403);
        abort_if($target->is_superadmin, 403);
        abort_if($target->isStaffOf($club), 403);

        Impersonation::start($target, route('clubs.members.show', [$club, $membership]));

        return redirect()->route('dashboard');
    }

    /**
     * Start browsing the app as one of the club's owners. Superadmin only.
     */
    public function storeOwner(Request $request, Club $club, User $owner): RedirectResponse
    {
        Gate::authorize('manage-platform');

        abort_if(Impersonation::isActive(), 403);
        abort_if($owner->is($request->user()), 403);
        abort_if($owner->is_superadmin, 403);

        Impersonation::start($owner, route('clubs.index'));

        return redirect()->route('clubs.dashboard', $club);
    }

    /**
     * Hand the session back to the staff member who started the impersonation.
     */
    public function destroy(): RedirectResponse
    {
        $returnTo = Impersonation::stop();

        return redirect()->to($returnTo ?? route('dashboard'));
    }
}
