<?php

namespace App\Livewire\Clubs;

use App\Actions\Demo\StartDemoSandbox;
use App\Enums\ClubSegment;
use App\Models\Club;
use App\Models\DemoRequest;
use App\Models\User;
use App\Services\AnalyticsEvents;
use App\Services\SpamProtection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Try the demo" on the landing pages: asks the visitor for their email
 * address and type of club in a modal, then gives them a private, filled
 * demo club and logs them in as its owner, without an account of their own.
 */
class StartDemo extends Component
{
    /** Sandboxes per visitor IP per hour; each one writes a few thousand rows. */
    public const int MAX_PER_HOUR = 3;

    /** Upper bound on live sandboxes, so a bot farm cannot fill the database. */
    public const int MAX_ACTIVE_SANDBOXES = 200;

    /** Remembers the visitor's sandbox owner (encrypted by the cookie middleware), so "Try the demo" reopens it. */
    public const string COOKIE = 'squadpass_demo';

    public string $email = '';

    /** A ClubSegment value, or DemoRequest::OTHER_CLUB_TYPE. */
    public string $club_type = '';

    /** What the club is, when the visitor chose "Something else". */
    public string $other_club_type = '';

    /**
     * Honeypot: hidden from people, so any value means a bot filled the form.
     */
    public string $website = '';

    #[Locked]
    public string $formStartedAt = '';

    /** Whether this visitor already has a live sandbox to return to. */
    #[Locked]
    public bool $canResume = false;

    /**
     * On a segment's landing page the type of club is preselected.
     */
    public function mount(SpamProtection $spamProtection, ?ClubSegment $segment = null): void
    {
        $this->club_type = $segment->value ?? '';
        $this->formStartedAt = $spamProtection->startedAtToken();
        $this->canResume = $this->resumableSandbox() !== null;
    }

    public function start(StartDemoSandbox $startDemoSandbox, SpamProtection $spamProtection, ?string $turnstileToken = null): void
    {
        if (Auth::check()) {
            $this->redirectRoute('dashboard');

            return;
        }

        if ($sandbox = $this->resumableSandbox()) {
            [$owner, $club] = $sandbox;

            $this->loginAndOpen($owner, $club);

            return;
        }

        $validated = $this->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'club_type' => ['required', Rule::in([...array_column(ClubSegment::cases(), 'value'), DemoRequest::OTHER_CLUB_TYPE])],
            'other_club_type' => ['nullable', 'required_if:club_type,'.DemoRequest::OTHER_CLUB_TYPE, 'string', 'max:100'],
        ], messages: [
            'other_club_type.required_if' => __('Tell us what kind of club you have.'),
        ], attributes: [
            'club_type' => __('Type of club'),
            'other_club_type' => __('Type of club'),
        ]);

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

        $segment = ClubSegment::tryFrom($validated['club_type']);

        DemoRequest::query()->create([
            'email' => Str::lower($validated['email']),
            'club_segment' => $segment,
            'other_club_type' => $segment ? null : $validated['other_club_type'],
        ]);

        Cookie::queue(self::COOKIE, (string) $owner->id, StartDemoSandbox::LIFETIME_HOURS * 60);

        app(AnalyticsEvents::class)->queue(AnalyticsEvents::DEMO_START);

        $this->loginAndOpen($owner, $club);
    }

    /**
     * The sandbox this browser started earlier, while it is still live.
     *
     * @return array{User, Club}|null
     */
    protected function resumableSandbox(): ?array
    {
        $ownerId = request()->cookie(self::COOKIE);

        if (! is_string($ownerId) || ! ctype_digit($ownerId)) {
            return null;
        }

        $owner = User::query()
            ->whereKey((int) $ownerId)
            ->where('demo_expires_at', '>', now())
            ->first();

        $club = $owner?->staffClubs()
            ->where('is_demo', true)
            ->where('demo_expires_at', '>', now())
            ->first();

        return $owner && $club ? [$owner, $club] : null;
    }

    /**
     * The choices in the modal's type picker: every segment, then "Something else".
     *
     * @return array<string, string>
     */
    public function clubTypeOptions(): array
    {
        $options = [];

        foreach (ClubSegment::cases() as $segment) {
            $options[$segment->value] = $segment->singularLabel();
        }

        return [...$options, DemoRequest::OTHER_CLUB_TYPE => __('Something else')];
    }

    protected function loginAndOpen(User $owner, Club $club): void
    {
        Auth::login($owner);
        session()->regenerate();

        $this->redirectRoute('clubs.dashboard', ['club' => $club]);
    }

    public function render(): View
    {
        return view('livewire.clubs.start-demo');
    }
}
