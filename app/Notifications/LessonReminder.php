<?php

namespace App\Notifications;

use App\Models\LessonOccurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent a club-chosen number of hours before a lesson to everyone attending it.
 */
class LessonReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LessonOccurrence $occurrence) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $club = $this->occurrence->club;
        $startsAt = $this->occurrence->starts_at->timezone($club->timezone);
        $lesson = $this->occurrence->lesson->name;

        return (new MailMessage)
            ->subject(__('Reminder: :lesson on :date', ['lesson' => $lesson, 'date' => $startsAt->translatedFormat('l j F')]))
            ->markdown('mail.notifications.lesson-reminder', [
                'club' => $club,
                'lesson' => $lesson,
                'startsAt' => $startsAt,
                'cancelUntil' => $this->occurrence->freeCancellationUntil()->timezone($club->timezone),
                'actionUrl' => route('portal.enrollments'),
            ]);
    }
}
