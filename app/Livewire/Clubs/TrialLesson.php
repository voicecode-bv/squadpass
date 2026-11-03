<?php

namespace App\Livewire\Clubs;

use App\Actions\Lessons\BookTrialLesson;
use App\Concerns\ProfileValidationRules;
use App\Enums\LessonOccurrenceStatus;
use App\Exceptions\TrialLessonException;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonOccurrence;
use App\Services\SpamProtection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public page where a visitor books a trial lesson without an account.
 *
 * @property-read Collection<int, LessonOccurrence> $occurrences
 */
#[Layout('layouts.auth')]
#[Title('Book a trial lesson')]
class TrialLesson extends Component
{
    use ProfileValidationRules;

    public const int MAX_ATTEMPTS_PER_MINUTE = 5;

    /** How far ahead visitors can pick a moment. */
    public const int DAYS_AHEAD = 14;

    /** Moments shown at most, so a busy club does not render an endless list. */
    public const int MAX_OCCURRENCES = 60;

    #[Locked]
    public Club $club;

    public ?int $occurrenceId = null;

    public string $first_name = '';

    public string $name_prefix = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    /**
     * Honeypot: hidden from people, so any value means a bot filled the form.
     */
    public string $website = '';

    #[Locked]
    public string $formStartedAt = '';

    /** Set once a free trial lesson is booked. */
    public bool $booked = false;

    /** Mollie sends the visitor back here with ?paid=1 after the checkout. */
    #[Url]
    public bool $paid = false;

    public function mount(Club $club, SpamProtection $spamProtection): void
    {
        abort_unless($club->trial_lessons_enabled, 404);

        $this->club = $club;
        $this->formStartedAt = $spamProtection->startedAtToken();
    }

    public function book(BookTrialLesson $bookTrialLesson, SpamProtection $spamProtection, ?string $turnstileToken = null): void
    {
        abort_unless($this->club->trial_lessons_enabled, 404);

        $throttleKey = 'trial-lesson:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        RateLimiter::hit($throttleKey);

        try {
            $validated = $this->validate([
                'occurrenceId' => ['required', 'integer'],
                ...$this->nameRules(),
                'email' => ['required', 'string', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:30'],
            ]);

            $spamProtection->ensureHuman($this->website, $this->formStartedAt, $turnstileToken, request()->ip());
        } catch (ValidationException $exception) {
            // A Turnstile token is single-use, so the widget needs a fresh one for the retry.
            $this->dispatch('turnstile-reset');

            throw $exception;
        }

        // Only moments offered on this page can be booked; anything else is a 404.
        $occurrence = $this->bookableOccurrences()->findOrFail((int) $validated['occurrenceId']);

        try {
            $checkoutUrl = $bookTrialLesson->handle($occurrence, $validated);
        } catch (TrialLessonException $exception) {
            $this->dispatch('turnstile-reset');
            $this->addError('occurrenceId', $exception->getMessage());
            unset($this->occurrences);

            return;
        }

        if ($checkoutUrl !== null) {
            $this->redirect($checkoutUrl);

            return;
        }

        $this->booked = true;
    }

    /**
     * Upcoming trial moments with room left, soonest first.
     *
     * @return Collection<int, LessonOccurrence>
     */
    #[Computed]
    public function occurrences(): Collection
    {
        return $this->bookableOccurrences()
            ->with('lesson')
            ->orderBy('starts_at')
            ->limit(self::MAX_OCCURRENCES)
            ->get();
    }

    public function selectedOccurrence(): ?LessonOccurrence
    {
        return $this->occurrenceId ? $this->occurrences->firstWhere('id', $this->occurrenceId) : null;
    }

    public function render(): View
    {
        return view('livewire.clubs.trial-lesson');
    }

    /**
     * @return Builder<LessonOccurrence>
     */
    protected function bookableOccurrences(): Builder
    {
        $trialLessons = Lesson::query()
            ->select('id')
            ->where('club_id', $this->club->id)
            ->where('trial_enabled', true)
            // A paid trial needs online payments; without them it is not offered.
            ->when(! $this->club->acceptsOnlinePayments(), fn ($query) => $query->where(
                fn ($priceQuery) => $priceQuery->whereNull('trial_price_cents')->orWhere('trial_price_cents', 0)
            ));

        return LessonOccurrence::query()
            ->where('club_id', $this->club->id)
            ->where('status', LessonOccurrenceStatus::Scheduled)
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addDays(self::DAYS_AHEAD))
            ->whereIn('lesson_id', $trialLessons)
            ->whereRaw('booked_count < COALESCE(capacity_override, (select capacity from lessons where lessons.id = lesson_occurrences.lesson_id))');
    }
}
