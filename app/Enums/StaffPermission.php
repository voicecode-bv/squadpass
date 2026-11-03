<?php

namespace App\Enums;

/**
 * Areas of a club a staff member may be given access to. Owners always have
 * every area plus the owner-only settings (plan, add-ons, integrations and staff).
 */
enum StaffPermission: string
{
    case Members = 'members';
    case MemberImport = 'member_import';
    case CheckIns = 'check_ins';
    case Lessons = 'lessons';
    case Billing = 'billing';
    case Forms = 'forms';
    case Tickets = 'tickets';
    case News = 'news';
    case Setup = 'setup';
    case ClubDetails = 'club_details';

    public function label(): string
    {
        return match ($this) {
            self::Members => __('Members'),
            self::MemberImport => __('Import members'),
            self::CheckIns => __('Check-in desk'),
            self::Lessons => __('Lessons & schedule'),
            self::Billing => __('Billing'),
            self::Forms => __('Forms'),
            self::Tickets => __('Support'),
            self::News => __('News'),
            self::Setup => __('Trainers & rooms'),
            self::ClubDetails => __('Club details'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Members => __('View, add and edit members.'),
            self::MemberImport => __('Add many members at once from a spreadsheet.'),
            self::CheckIns => __('Check members in at the desk and the kiosk.'),
            self::Lessons => __('Manage lessons, the schedule and participants.'),
            self::Billing => __('Strip cards, subscriptions, invoices and payments.'),
            self::Forms => __('Build forms and read submissions.'),
            self::Tickets => __('Answer support tickets from members.'),
            self::News => __('Post news and announcements for members.'),
            self::Setup => __('Invite trainers and manage rooms.'),
            self::ClubDetails => __('Name, photo, online registration, cancellation deadline and credit expiry.'),
        };
    }

    /**
     * Starting points for a new staff member; the owner can still tick boxes on or off.
     *
     * @return array<string, array{label: string, permissions: list<self>}>
     */
    public static function presets(): array
    {
        return [
            'manager' => ['label' => __('Manager'), 'permissions' => self::cases()],
            'desk' => ['label' => __('Front desk'), 'permissions' => [self::Members, self::CheckIns, self::Lessons, self::News]],
            'finance' => ['label' => __('Finance'), 'permissions' => [self::Members, self::Billing]],
            'support' => ['label' => __('Support'), 'permissions' => [self::Members, self::Tickets, self::News]],
        ];
    }

    /**
     * @param  array<int, string>|null  $values
     * @return list<self>
     */
    public static function fromValues(?array $values): array
    {
        return array_values(array_filter(array_map(fn (string $value): ?self => self::tryFrom($value), $values ?? [])));
    }
}
