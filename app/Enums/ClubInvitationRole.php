<?php

namespace App\Enums;

enum ClubInvitationRole: string
{
    case Trainer = 'trainer';
    case Member = 'member';

    /** A colleague who logs in to manage the club with the permissions the owner ticked. */
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Trainer => __('Trainer'),
            self::Member => __('Member'),
            self::Staff => __('Staff member'),
        };
    }

    /**
     * Mail subject and heading of the invitation.
     */
    public function invitedTitle(string $club): string
    {
        return match ($this) {
            self::Trainer => __('You have been invited as a trainer at :club', ['club' => $club]),
            self::Member => __('You have been invited as a member of :club', ['club' => $club]),
            self::Staff => __('You have been invited to help manage :club', ['club' => $club]),
        };
    }

    public function acceptHeading(): string
    {
        return match ($this) {
            self::Trainer => __('Trainer invitation'),
            self::Member => __('Membership invitation'),
            self::Staff => __('Staff invitation'),
        };
    }

    public function acceptIntro(string $club): string
    {
        return match ($this) {
            self::Trainer => __('You have been invited to become a trainer at :club.', ['club' => $club]),
            self::Member => __('You have been invited to become a member of :club.', ['club' => $club]),
            self::Staff => __('You have been invited to help manage :club.', ['club' => $club]),
        };
    }

    public function acceptAsLoggedIn(string $name, string $club): string
    {
        return match ($this) {
            self::Trainer => __('You are logged in as :name. Accept to become a trainer at :club.', ['name' => $name, 'club' => $club]),
            self::Member => __('You are logged in as :name. Accept to become a member of :club.', ['name' => $name, 'club' => $club]),
            self::Staff => __('You are logged in as :name. Accept to help manage :club.', ['name' => $name, 'club' => $club]),
        };
    }

    public function accepted(string $club): string
    {
        return match ($this) {
            self::Trainer => __('You are now a trainer at :club.', ['club' => $club]),
            self::Member => __('You are now a member of :club.', ['club' => $club]),
            self::Staff => __('You now help manage :club.', ['club' => $club]),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Trainer => 'academic-cap',
            self::Member => 'users',
            self::Staff => 'building-storefront',
        };
    }
}
