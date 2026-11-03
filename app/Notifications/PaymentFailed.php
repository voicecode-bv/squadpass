<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a member whose recurring subscription payment failed.
 */
class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $club = $this->payment->club;

        return (new MailMessage)
            ->subject(__('Your payment to :club did not go through', ['club' => $club->name]))
            ->markdown('mail.notifications.payment-failed', [
                'club' => $club,
                'amount' => $this->payment->formattedAmount(),
                'actionUrl' => route('portal.subscriptions'),
            ]);
    }
}
