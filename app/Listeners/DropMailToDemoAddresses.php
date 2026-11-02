<?php

namespace App\Listeners;

use Database\Seeders\DemoClubSeeder;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

class DropMailToDemoAddresses
{
    /**
     * Cancel any message addressed to a demo sandbox account. Those addresses
     * cannot receive mail anyway; dropping the message keeps the mail log
     * clean and the sender reputation intact.
     */
    public function handle(MessageSending $event): bool
    {
        $recipients = [...$event->message->getTo(), ...$event->message->getCc(), ...$event->message->getBcc()];

        foreach ($recipients as $recipient) {
            if (self::isDemoAddress($recipient)) {
                return false;
            }
        }

        return true;
    }

    protected static function isDemoAddress(Address $address): bool
    {
        return str_ends_with(strtolower($address->getAddress()), '@'.DemoClubSeeder::SANDBOX_EMAIL_DOMAIN);
    }
}
