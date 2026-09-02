<?php

namespace App\Livewire\Lessons;

use App\Models\Club;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\Trainer;
use App\Services\Lessons\OccurrenceGenerator;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Lesson')]
class Form extends Component
{
    public Club $club;

    public ?Lesson $lesson = null;

    public string $name = '';

    public string $description = '';

    public ?int $room_id = null;

    public int $capacity = 10;

    /**
     * One weekly time slot per row; each row becomes its own lesson.
     *
     * @var array<int, array{day_of_week: int|string, start_time: string, duration_minutes: int|string}>
     */
    public array $schedules = [];

    public string $starts_on = '';

    public string $ends_on = '';

    public bool $active = true;

    /** @var array<int, int|string> */
    public array $trainer_ids = [];

    public function mount(Club $club, ?Lesson $lesson = null): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
        $this->starts_on = now($club->timezone)->toDateString();
        $this->schedules = [$this->defaultScheduleRow()];

        if ($lesson && $lesson->exists) {
            abort_unless($lesson->club_id === $club->id, 404);

            $this->lesson = $lesson;
            $this->name = $lesson->name;
            $this->description = $lesson->description ?? '';
            $this->room_id = $lesson->room_id;
            $this->capacity = $lesson->capacity;
            $this->schedules = [[
                'day_of_week' => $lesson->day_of_week,
                'start_time' => substr($lesson->start_time, 0, 5),
                'duration_minutes' => $lesson->duration_minutes,
            ]];
            $this->starts_on = $lesson->starts_on->toDateString();
            $this->ends_on = $lesson->ends_on?->toDateString() ?? '';
            $this->active = $lesson->active;
            $this->trainer_ids = $lesson->trainers()->pluck('trainers.id')->all();
        }
    }

    public function addSchedule(): void
    {
        $this->schedules[] = $this->defaultScheduleRow();
    }

    public function removeSchedule(int $index): void
    {
        if ($this->lesson || count($this->schedules) <= 1) {
            return;
        }

        unset($this->schedules[$index]);
        $this->schedules = array_values($this->schedules);
    }

    public function save(OccurrenceGenerator $generator): void
    {
        $this->authorize('manage', $this->club);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'room_id' => [
                'nullable',
                Rule::exists('rooms', 'id')->where('club_id', $this->club->id),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'active' => ['boolean'],
            'trainer_ids' => ['array'],
            'trainer_ids.*' => ['integer'],
        ]);

        $trainerIds = Trainer::query()
            ->where('club_id', $this->club->id)
            ->whereIn('id', $validated['trainer_ids'])
            ->pluck('id');

        $shared = [
            'club_id' => $this->club->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'room_id' => $validated['room_id'] ?: null,
            'capacity' => $validated['capacity'],
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'] ?: null,
            'active' => $validated['active'],
        ];

        if ($this->lesson) {
            $schedule = $validated['schedules'][0];

            $scheduleChanged = $this->lesson->day_of_week !== (int) $schedule['day_of_week']
                || substr($this->lesson->start_time, 0, 5) !== $schedule['start_time']
                || $this->lesson->duration_minutes !== (int) $schedule['duration_minutes'];

            $this->lesson->update($shared + $this->scheduleAttributes($schedule));
            $this->lesson->trainers()->sync($trainerIds);

            $scheduleChanged
                ? $generator->resyncFutureOccurrences($this->lesson)
                : $generator->generateForLesson($this->lesson->refresh());

            Flux::toast(variant: 'success', text: __('Lesson updated.'));
        } else {
            foreach ($validated['schedules'] as $schedule) {
                $lesson = Lesson::create($shared + $this->scheduleAttributes($schedule));
                $lesson->trainers()->sync($trainerIds);

                $generator->generateForLesson($lesson);
            }

            Flux::toast(variant: 'success', text: trans_choice('Lesson created.|:count lessons created.', count($validated['schedules']), [
                'count' => count($validated['schedules']),
            ]));
        }

        $this->redirectRoute('clubs.lessons', ['club' => $this->club], navigate: true);
    }

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function availableRooms(): Collection
    {
        return Room::query()
            ->where('club_id', $this->club->id)
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Trainer>
     */
    #[Computed]
    public function availableTrainers(): Collection
    {
        return Trainer::query()
            ->where('club_id', $this->club->id)
            ->where('active', true)
            ->with('user')
            ->get()
            ->sortBy(fn (Trainer $trainer) => $trainer->user->name)
            ->values();
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
        return view('livewire.lessons.form');
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

    /**
     * @param  array{day_of_week: int|string, start_time: string, duration_minutes: int|string}  $schedule
     * @return array{day_of_week: int, start_time: string, duration_minutes: int}
     */
    protected function scheduleAttributes(array $schedule): array
    {
        return [
            'day_of_week' => (int) $schedule['day_of_week'],
            'start_time' => $schedule['start_time'].':00',
            'duration_minutes' => (int) $schedule['duration_minutes'],
        ];
    }
}
