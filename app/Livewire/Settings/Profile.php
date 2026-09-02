<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use App\Jobs\SyncMoneybirdContact;
use App\Models\Membership;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules, WithFileUploads;

    public string $name = '';

    public string $email = '';

    public ?TemporaryUploadedFile $avatar = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $contactDetailsChanged = $user->isDirty(['name', 'email']);

        $user->save();

        if ($contactDetailsChanged) {
            $this->resyncMoneybirdContacts($user);
        }

        Flux::toast(variant: 'success', text: __('Profile updated.'));
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

    /**
     * Store the cropped avatar uploaded from the browser.
     */
    public function saveAvatar(): void
    {
        $this->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->update([
            'avatar_path' => $this->avatar->store('avatars', 'public'),
        ]);

        $this->avatar = null;

        Flux::toast(variant: 'success', text: __('Avatar updated.'));
    }

    public function deleteAvatar(): void
    {
        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        Flux::toast(variant: 'success', text: __('Avatar removed.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        $user = Auth::user();

        return $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        $user = Auth::user();

        return ! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail();
    }
}
