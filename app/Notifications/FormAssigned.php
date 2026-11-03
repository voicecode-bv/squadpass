<?php

namespace App\Notifications;

use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Asks a member to fill in a form that staff assigned to them.
 */
class FormAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Form $form) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $club = $this->form->club;

        return (new MailMessage)
            ->subject(__(':club asks you to fill in :form', ['club' => $club->name, 'form' => $this->form->name]))
            ->markdown('mail.notifications.form-assigned', [
                'club' => $club,
                'formName' => $this->form->name,
                'actionUrl' => route('portal.forms.fill', $this->form),
            ]);
    }
}
