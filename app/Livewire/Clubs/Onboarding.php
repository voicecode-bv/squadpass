<?php

namespace App\Livewire\Clubs;

use App\Actions\Invitations\InviteToClub;
use App\Enums\AddonKey;
use App\Enums\BillingInterval;
use App\Enums\ClubInvitationRole;
use App\Exceptions\ClubInvitationException;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\StripCardPlan;
use App\Models\SubscriptionPlan;
use App\Models\Trainer;
use App\Services\Lessons\OccurrenceGenerator;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Set up your club')]
class Onboarding extends Component
{
    public const int LAST_STEP = 5;

    public Club $club;

    #[Url]
    public int $step = 1;

    public string $room_name = '';

    public string $trainer_email = '';

    public string $lesson_name = '';

    public ?int $lesson_room_id = null;

    public int $lesson_capacity = 10;

    /**
     * One weekly time slot per row; each row becomes its own lesson.
     *
     * @var array<int, array{day_of_week: int|string, start_time: string, duration_minutes: int|string}>
     */
    public array $lesson_schedules = [];

    public string $plan_name = '';

    public string $plan_price = '';

    public string $plan_interval = 'monthly';

    public ?int $plan_credits = null;

    public string $card_name = '';

    public int $card_strips = 10;

    public string $card_price = '';

    public ?int $card_validity_months = 12;

    public string $mollie_api_key = '';

    public function mount(Club $club): void
    {
        $this->authorize('update', $club);

        $this->club = $club;
        $this->step = min(max($this->step, 1), self::LAST_STEP);
        $this->mollie_api_key = $club->mollie_api_key ?? '';
        $this->lesson_schedules = [$this->defaultScheduleRow()];
    }

    public function addLessonSchedule(): void
    {
        $this->lesson_schedules[] = $this->defaultScheduleRow();
    }

    public function removeLessonSchedule(int $index): void
    {
        if (count($this->lesson_schedules) <= 1) {
            return;
        }

        unset($this->lesson_schedules[$index]);
        $this->lesson_schedules = array_values($this->lesson_schedules);
    }

    public function next(): void
    {
        $this->step = min($this->step + 1, self::LAST_STEP);
    }

    public function back(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    public function finish(): void
    {
        $this->authorize('update', $this->club);

        $this->club->update(['onboarded_at' => now()]);

        Flux::toast(variant: 'success', text: __('Your club is ready to go!'));

        $this->redirectRoute('clubs.members', ['club' => $this->club], navigate: true);
    }

    public function addRoom(): void
    {
        $this->authorize('update', $this->club);

        $validated = $this->validate([
            'room_name' => [
                'required', 'string', 'max:255',
                Rule::unique('rooms', 'name')->where('club_id', $this->club->id),
            ],
        ]);

        Room::create([
            'club_id' => $this->club->id,
            'name' => $validated['room_name'],
        ]);

        $this->reset('room_name');
        unset($this->rooms);
    }

    public function inviteTrainer(InviteToClub $inviteToClub): void
    {
        $this->authorize('update', $this->club);

        $validated = $this->validate([
            'trainer_email' => ['required', 'email'],
        ]);

        try {
            $inviteToClub->handle($this->club, $validated['trainer_email'], ClubInvitationRole::Trainer, Auth::user());
        } catch (ClubInvitationException $exception) {
            $this->addError('trainer_email', $exception->getMessage());

            return;
        }

        $this->reset('trainer_email');
        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation sent to :email.', ['email' => $validated['trainer_email']]));
    }

    public function createLesson(OccurrenceGenerator $generator): void
    {
        $this->authorize('update', $this->club);

        $validated = $this->validate([
            'lesson_name' => ['required', 'string', 'max:255'],
            'lesson_room_id' => [
                'nullable',
                Rule::exists('rooms', 'id')->where('club_id', $this->club->id),
            ],
            'lesson_capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'lesson_schedules' => ['required', 'array', 'min:1'],
            'lesson_schedules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'lesson_schedules.*.start_time' => ['required', 'date_format:H:i'],
            'lesson_schedules.*.duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
        ]);

        foreach ($validated['lesson_schedules'] as $schedule) {
            $lesson = Lesson::create([
                'club_id' => $this->club->id,
                'name' => $validated['lesson_name'],
                'room_id' => $validated['lesson_room_id'] ?: null,
                'capacity' => $validated['lesson_capacity'],
                'day_of_week' => (int) $schedule['day_of_week'],
                'start_time' => $schedule['start_time'].':00',
                'duration_minutes' => (int) $schedule['duration_minutes'],
                'starts_on' => now($this->club->timezone)->toDateString(),
                'active' => true,
            ]);

            $generator->generateForLesson($lesson);
        }

        $this->reset('lesson_name', 'lesson_room_id');
        $this->lesson_schedules = [$this->defaultScheduleRow()];
        unset($this->lessons);

        Flux::toast(variant: 'success', text: trans_choice('Lesson created.|:count lessons created.', count($validated['lesson_schedules']), [
            'count' => count($validated['lesson_schedules']),
        ]));
    }

    public function createSubscriptionPlan(): void
    {
        $this->authorize('update', $this->club);

        $validated = $this->validate([
            'plan_name' => ['required', 'string', 'max:255'],
            'plan_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'plan_interval' => ['required', Rule::enum(BillingInterval::class)],
            'plan_credits' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        SubscriptionPlan::create([
            'club_id' => $this->club->id,
            'name' => $validated['plan_name'],
            'price_cents' => (int) round($validated['plan_price'] * 100),
            'interval' => $validated['plan_interval'],
            'credits' => $validated['plan_credits'],
        ]);

        $this->reset('plan_name', 'plan_price', 'plan_credits');
        unset($this->subscriptionPlans);
    }

    public function createStripCardPlan(): void
    {
        $this->authorize('update', $this->club);

        $validated = $this->validate([
            'card_name' => ['required', 'string', 'max:255'],
            'card_strips' => ['required', 'integer', 'min:1', 'max:1000'],
            'card_price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'card_validity_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        StripCardPlan::create([
            'club_id' => $this->club->id,
            'name' => $validated['card_name'],
            'strips' => $validated['card_strips'],
            'price_cents' => (int) round($validated['card_price'] * 100),
            'validity_months' => $validated['card_validity_months'],
        ]);

        $this->reset('card_name', 'card_price');
        unset($this->stripCardPlans);
    }

    public function saveMollieKey(): void
    {
        $this->authorize('update', $this->club);

        abort_unless($this->club->hasAddon(AddonKey::Mollie), 403);

        $validated = $this->validate([
            'mollie_api_key' => ['required', 'string', 'starts_with:test_,live_'],
        ]);

        $this->club->update(['mollie_api_key' => $validated['mollie_api_key']]);

        Flux::toast(variant: 'success', text: __('Mollie API key saved.'));
    }

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()->where('club_id', $this->club->id)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Trainer>
     */
    #[Computed]
    public function trainers(): Collection
    {
        return Trainer::query()->where('club_id', $this->club->id)->with('user')->get();
    }

    /**
     * @return Collection<int, ClubInvitation>
     */
    #[Computed]
    public function pendingInvitations(): Collection
    {
        return ClubInvitation::query()
            ->where('club_id', $this->club->id)
            ->forRole(ClubInvitationRole::Trainer)
            ->pending()
            ->get();
    }

    /**
     * @return Collection<int, Lesson>
     */
    #[Computed]
    public function lessons(): Collection
    {
        return Lesson::query()->where('club_id', $this->club->id)->with('room')->get();
    }

    /**
     * @return Collection<int, SubscriptionPlan>
     */
    #[Computed]
    public function subscriptionPlans(): Collection
    {
        return SubscriptionPlan::query()->where('club_id', $this->club->id)->get();
    }

    /**
     * @return Collection<int, StripCardPlan>
     */
    #[Computed]
    public function stripCardPlans(): Collection
    {
        return StripCardPlan::query()->where('club_id', $this->club->id)->get();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function stepLabels(): array
    {
        return [
            1 => __('Rooms'),
            2 => __('Trainers'),
            3 => __('First lesson'),
            4 => __('Offering'),
            5 => __('Payments'),
        ];
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function dayOptions(): array
    {
        return collect(range(1, 7))
            ->mapWithKeys(fn (int $day) => [$day => Carbon::now()->startOfWeek()->addDays($day - 1)->dayName])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.clubs.onboarding');
    }

    /**
     * @return array{day_of_week: int, start_time: string, duration_minutes: int}
     */
    protected function defaultScheduleRow(): array
    {
        return [
            'day_of_week' => 1,
            'start_time' => '19:00',
            'duration_minutes' => 60,
        ];
    }
}
