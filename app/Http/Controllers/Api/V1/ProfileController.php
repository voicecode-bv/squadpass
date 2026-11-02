<?php

namespace App\Http\Controllers\Api\V1;

use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Jobs\SyncMoneybirdContact;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    use ProfileValidationRules;

    /**
     * Update the member's own name and email address. App versions from
     * before the name was split still send a single "name"; the User model
     * splits it into first name, prefix and last name.
     */
    public function update(Request $request): UserResource
    {
        $user = $request->user();

        $usesLegacyName = $request->has('name') && ! $request->has('first_name');

        $validated = $request->validate($usesLegacyName
            ? ['name' => ['required', 'string', 'max:255'], 'email' => $this->emailRules($user->id)]
            : $this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $contactDetailsChanged = $user->isDirty(['name', 'first_name', 'name_prefix', 'last_name', 'email']);

        // A name sent in parts was entered by the member; a single legacy name is still split by us.
        if (! $usesLegacyName) {
            $user->name_confirmed_at = now();
        }

        $user->save();

        if ($contactDetailsChanged) {
            $this->resyncMoneybirdContacts($user);
        }

        return new UserResource($user);
    }

    /**
     * Change the member's password. Existing access tokens keep working, so
     * the app does not have to sign the member in again.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The provided password was incorrect.')],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        return response()->json(['message' => __('Password updated.')]);
    }

    /**
     * Push changed name/email to Moneybird for every club that syncs contacts.
     */
    protected function resyncMoneybirdContacts(User $user): void
    {
        $user->memberships()
            ->with('club')
            ->get()
            ->filter(fn (Membership $membership) => $membership->club->syncsMoneybirdContacts())
            ->each(fn (Membership $membership) => SyncMoneybirdContact::dispatch($membership));
    }
}
