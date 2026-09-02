<?php

namespace App\Mail;

use App\Enums\ClubInvitationRole;
use App\Models\ClubInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClubInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ClubInvitation $invitation) {}

    public function envelope(): Envelope
    {
        $club = $this->invitation->club->name;

        return new Envelope(
            subject: $this->invitation->role === ClubInvitationRole::Trainer
                ? __('You have been invited as a trainer at :club', ['club' => $club])
                : __('You have been invited as a member of :club', ['club' => $club]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.club-invitation',
            with: [
                'club' => $this->invitation->club,
                'role' => $this->invitation->role,
                'acceptUrl' => route('invitations.accept', $this->invitation->token),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
