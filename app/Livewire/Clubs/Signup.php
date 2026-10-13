<?php

namespace App\Livewire\Clubs;

use App\Actions\Clubs\CreateClub;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\SpamProtection;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One-step signup on the landing page: creates the owner's account and their
 * club together, so a new club owner lands straight in the club onboarding.
 */
class Signup extends Component
{
    use ProfileValidationRules;

    public const int MAX_ATTEMPTS_PER_MINUTE = 5;

    public string $club_name = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    /**
     * Honeypot: hidden from people, so any value means a bot filled the form.
     */
    public string $website = '';

    #[Locked]
    public string $formStartedAt = '';

    public function mount(SpamProtection $spamProtection): void
    {
        $this->formStartedAt = $spamProtection->startedAtToken();
    }

    public function signup(?string $turnstileToken = null): void
    {
        $throttleKey = 'club-signup:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        RateLimiter::hit($throttleKey);

        try {
            $validated = $this->validate([
                'club_name' => ['required', 'string', 'max:255'],
                ...$this->profileRules(),
                'password' => ['required', 'string', Password::default()],
            ]);

            app(SpamProtection::class)->ensureHuman($this->website, $this->formStartedAt, $turnstileToken, request()->ip());
        } catch (ValidationException $exception) {
            // A Turnstile token is single-use, so the widget needs a fresh one for the retry.
            $this->dispatch('turnstile-reset');

            throw $exception;
        }

        $createClub = app(CreateClub::class);

        [$user, $club] = DB::transaction(function () use ($validated, $createClub): array {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            return [$user, $createClub->handle($validated['club_name'], $user)];
        });

        event(new Registered($user));

        Auth::login($user);

        session()->regenerate();

        // Unverified owners pass through the verification notice first and
        // continue to the onboarding once their email is confirmed.
        session()->put('url.intended', route('clubs.onboarding', $club));

        $this->redirectRoute('clubs.onboarding', ['club' => $club]);
    }

    public function render(): View
    {
        return view('livewire.clubs.signup');
    }
}
