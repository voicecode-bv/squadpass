<?php

namespace App\Livewire\Clubs;

use App\Actions\Demo\StartDemoSandbox;
use App\Models\Club;
use App\Services\SpamProtection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Try the demo" on the landing pages: gives the visitor a private, filled
 * demo club and logs them in as its owner, without an account of their own.
 */
class StartDemo extends Component
{
    /** Sandboxes per visitor IP per hour; each one writes a few thousand rows. */
    public const int MAX_PER_HOUR = 3;

    /** Upper bound on live sandboxes, so a bot farm cannot fill the database. */
    public const int MAX_ACTIVE_SANDBOXES = 200;

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

    public function start(StartDemoSandbox $startDemoSandbox, SpamProtection $spamProtection, ?string $turnstileToken = null): void
    {
        if (Auth::check()) {
            $this->redirectRoute('dashboard');

            return;
        }

        $throttleKey = 'demo-sandbox:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages([
                SpamProtection::ERROR_KEY => __('You have started the maximum number of demos for this hour. Try again in :minutes minutes, or create your own club right away.', [
                    'minutes' => max(1, (int) ceil(RateLimiter::availableIn($throttleKey) / 60)),
                ]),
            ]);
        }

        try {
            $spamProtection->ensureHuman($this->website, $this->formStartedAt, $turnstileToken, request()->ip());
        } catch (ValidationException $exception) {
            $this->dispatch('turnstile-reset');

            throw $exception;
        }

        if (Club::query()->where('demo_expires_at', '>', now())->count() >= self::MAX_ACTIVE_SANDBOXES) {
            throw ValidationException::withMessages([
                SpamProtection::ERROR_KEY => __('The demo is very busy right now. Please try again later, or create your own club right away.'),
            ]);
        }

        RateLimiter::hit($throttleKey, 3600);

        [$owner, $club] = $startDemoSandbox->handle();

        Auth::login($owner);
        session()->regenerate();

        $this->redirectRoute('clubs.dashboard', ['club' => $club]);
    }

    public function render(): View
    {
        return view('livewire.clubs.start-demo');
    }
}
