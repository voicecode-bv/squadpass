<?php

namespace App\Notifications;

use App\Models\Club;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Warns a member that unused lesson credits expire within a few days.
 */
class CreditsExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Club $club, public int $credits, public CarbonInterface $expiresAt) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresAt = $this->expiresAt->copy()->timezone($this->club->timezone);

        return (new MailMessage)
            ->subject(trans_choice('{1} Your credit at :club expires soon|[2,*] Your :count credits at :club expire soon', $this->credits, ['club' => $this->club->name]))
            ->markdown('mail.notifications.credits-expiring', [
                'club' => $this->club,
                'credits' => $this->credits,
                'expiresAt' => $expiresAt,
                'actionUrl' => route('portal.schedule'),
            ]);
    }
}
