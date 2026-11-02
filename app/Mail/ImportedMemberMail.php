<?php

namespace App\Mail;

use App\Models\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to someone a club added through a member list import. A new account
 * has no known password yet, so it is pointed to "forgot password" to set one.
 */
class ImportedMemberMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Membership $membership, public bool $accountCreated) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You are now a member of :club', ['club' => $this->membership->club->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.imported-member',
            with: [
                'club' => $this->membership->club,
                'email' => $this->membership->user->email,
                'membershipNumber' => $this->membership->membership_number,
                'accountCreated' => $this->accountCreated,
                'actionUrl' => $this->accountCreated ? route('password.request') : route('dashboard'),
            ],
        );
    }
}
