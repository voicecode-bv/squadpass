<?php

namespace App\Console\Commands;

use App\Enums\LessonOccurrenceStatus;
use App\Models\Club;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Notifications\LessonReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendLessonReminders extends Command
{
    protected $signature = 'lessons:send-reminders';

    protected $description = 'Email members a reminder before their lessons, for clubs that turned reminders on';

    public function handle(): int
    {
        $sent = 0;

        // One pass per distinct reminder setting keeps the date comparison in PHP,
        // so the query stays portable and can use the (reminders_sent_at, starts_at) index.
        $reminderHours = Club::query()
            ->whereNotNull('lesson_reminder_hours')
            ->distinct()
            ->pluck('lesson_reminder_hours');

        foreach ($reminderHours as $hours) {
            LessonOccurrence::query()
                ->whereNull('reminders_sent_at')
                ->where('status', LessonOccurrenceStatus::Scheduled)
                ->where('starts_at', '>', now())
                ->where('starts_at', '<=', now()->addHours($hours))
                ->whereIn('club_id', Club::query()->select('id')->where('lesson_reminder_hours', $hours))
                ->with(['club', 'lesson'])
                ->chunkById(100, function ($occurrences) use (&$sent): void {
                    foreach ($occurrences as $occurrence) {
                        $sent += $this->remind($occurrence);
                    }
                });
        }

        $this->info("Sent {$sent} lesson reminders.");

        return self::SUCCESS;
    }

    /**
     * Claim the occurrence first, so overlapping runs never remind anyone twice.
     */
    private function remind(LessonOccurrence $occurrence): int
    {
        $claimed = LessonOccurrence::query()
            ->whereKey($occurrence->id)
            ->whereNull('reminders_sent_at')
            ->update(['reminders_sent_at' => now()]) === 1;

        if (! $claimed) {
            return 0;
        }

        $recipients = $occurrence->participants()
            ->map(fn (LessonEnrollment $enrollment) => $enrollment->user)
            ->filter(fn ($user) => $user !== null && $user->lesson_reminders)
            ->unique('id');

        Notification::send($recipients, new LessonReminder($occurrence));

        return $recipients->count();
    }
}
