<?php

namespace App\Notifications;

use App\Models\LessonEnrollment;
use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the club's staff that a visitor booked a trial lesson.
 */
class NewTrialLead extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LessonEnrollment $enrollment) {}

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
        $visitor = $this->enrollment->user;
        $startsAt = $occurrence->starts_at->timezone($club->timezone);

        $membership = Membership::query()
            ->where('club_id', $club->id)
            ->where('user_id', $visitor->id)
            ->first();

        return (new MailMessage)
            ->subject(__('New trial lesson: :name', ['name' => $visitor->name]))
            ->markdown('mail.notifications.new-trial-lead', [
                'club' => $club,
                'name' => $visitor->name,
                'email' => $visitor->email,
                'phone' => $membership?->phone,
                'lesson' => $occurrence->lesson->name,
                'startsAt' => $startsAt,
                'actionUrl' => $membership
                    ? route('clubs.members.show', [$club, $membership])
                    : route('clubs.members', ['club' => $club, 'status' => 'trial']),
            ]);
    }
}
