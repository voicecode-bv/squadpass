<?php

namespace App\Actions\CheckIns;

use App\Events\CheckIns\CheckInRefused;
use App\Events\CheckIns\MemberCheckedIn;
use App\Exceptions\CheckInException;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\User;
use App\Services\CheckIns\CheckInCode;
use Throwable;

/**
 * Check a member in from a scanned QR code, in either direction:
 * the member scans the club's kiosk code, or the desk scans the member's code.
 *
 * A scanned check-in is always a free visit: no lesson occurrence is attached,
 * so lesson participation keeps running through reservations. Because a visit
 * without an occurrence has no unique constraint to lean on, a scan that
 * repeats within DUPLICATE_WINDOW_MINUTES is rejected instead of burning a
 * second strip.
 */
class CheckInWithCode
{
    public const DUPLICATE_WINDOW_MINUTES = 15;

    public function __construct(
        private CheckInCode $codes,
        private CheckInMember $checkInMember,
    ) {}

    /**
     * A member scans the rotating code on a club's check-in kiosk.
     *
     * @throws CheckInException
     */
    public function fromClubCode(User $member, string $code): CheckIn
    {
        $club = $this->codes->clubFrom($code);

        // The member is standing in front of the club's kiosk screen, which
        // reports both outcomes: the visit, or why it did not happen.
        try {
            $this->guardAgainstDuplicate($member, $club);

            $checkIn = $this->checkInMember->handle($member, $club);
        } catch (CheckInException $exception) {
            $this->announce(new CheckInRefused($club, $member, $exception->getMessage()));

            throw $exception;
        }

        $this->announce(new MemberCheckedIn($checkIn));

        return $checkIn;
    }

    /**
     * The check-in desk scans the rotating code in a member's app.
     *
     * @throws CheckInException
     */
    public function fromMemberCode(Club $club, string $code, User $checkedInBy): CheckIn
    {
        $member = $this->codes->memberFrom($code);

        $this->guardAgainstDuplicate($member, $club);

        return $this->checkInMember->handle($member, $club, checkedInBy: $checkedInBy);
    }

    /**
     * Broadcasts exist for the kiosk screen alone. A screen that misses one is
     * a cosmetic loss, so a broadcaster that is down or slow may never change
     * what the member's app is told — not the visit it registered, and not the
     * reason it was refused.
     */
    private function announce(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @throws CheckInException
     */
    private function guardAgainstDuplicate(User $member, Club $club): void
    {
        $recent = CheckIn::query()
            ->where('user_id', $member->id)
            ->where('club_id', $club->id)
            ->where('checked_in_at', '>=', now()->subMinutes(self::DUPLICATE_WINDOW_MINUTES))
            ->exists();

        throw_if($recent, CheckInException::recentlyCheckedIn());
    }
}
