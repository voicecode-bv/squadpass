<?php

namespace App\Notifications;

use App\Models\NewsPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Emails a club's members when staff publish a news post with "notify members" on.
 */
class NewsPublished extends Notification implements ShouldQueue
{
    use Queueable;

    /** Characters of the post shown in the email; the full post is in the portal. */
    public const int EXCERPT_LENGTH = 400;

    public function __construct(public NewsPost $post) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('News from :club: :title', ['club' => $this->post->club->name, 'title' => $this->post->title]))
            ->markdown('mail.notifications.news-published', [
                'club' => $this->post->club,
                'title' => $this->post->title,
                'excerpt' => Str::limit($this->post->body, self::EXCERPT_LENGTH),
                'actionUrl' => route('portal.news'),
            ]);
    }
}
