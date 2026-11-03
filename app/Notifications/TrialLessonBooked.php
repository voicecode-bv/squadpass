<?php

namespace App\Notifications;

use App\Models\LessonEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms a booked trial lesson to the visitor. A new account has no password
 * yet, so the email points to "forgot password" to choose one.
 */
class TrialLessonBooked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LessonEnrollment $enrollment, public bool $accountCreated) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $occurrence = $this->enrollment->occurrence;
        $club = $occurrence->club;
        $startsAt = $occurrence->starts_at->timezone($club->timezone);

        return (new MailMessage)
            ->subject(__('Your trial lesson at :club is booked', ['club' => $club->name]))
            ->markdown('mail.notifications.trial-lesson-booked', [
                'club' => $club,
                'lesson' => $occurrence->lesson->name,
                'startsAt' => $startsAt,
                'accountCreated' => $this->accountCreated,
                'actionUrl' => $this->accountCreated ? route('password.request') : route('login'),
            ]);
    }
}
