<?php

namespace App\Services\CheckIns;

use App\Exceptions\CheckInException;
use App\Models\Club;
use App\Models\User;

/**
 * Mints and verifies the short-lived codes behind the check-in QR codes.
 *
 * A code carries no state of its own: it is the subject (a club or a member),
 * its id and the current time window, signed with the app key. The window
 * rotates every PERIOD_SECONDS, so a photo of a QR code is worthless a minute
 * later, while a device with a slightly slow clock still gets in through the
 * one-window grace. Codes stay within the QR alphanumeric character set
 * (digits, uppercase, dots) to keep the printed code as coarse as possible.
 */
class CheckInCode
{
    public const PERIOD_SECONDS = 30;

    private const CLUB = 'C';

    private const MEMBER = 'M';

    /**
     * How many expired windows are still accepted, to absorb clock drift and
     * the seconds between rendering a code and scanning it.
     */
    private const GRACE_WINDOWS = 1;

    private const SIGNATURE_LENGTH = 16;

    /**
     * The code for the club's rotating QR code, scanned by a member's app.
     */
    public function forClub(Club $club): string
    {
        return $this->mint(self::CLUB, $club->id, $this->currentWindow());
    }

    /**
     * The code for a member's personal rotating QR code, scanned at the desk.
     */
    public function forMember(User $member): string
    {
        return $this->mint(self::MEMBER, $member->id, $this->currentWindow());
    }

    /**
     * Seconds left before the current codes rotate.
     */
    public function secondsUntilRotation(): int
    {
        return self::PERIOD_SECONDS - (now()->getTimestamp() % self::PERIOD_SECONDS);
    }

    /**
     * @throws CheckInException when the code is malformed, expired or unknown
     */
    public function clubFrom(string $code): Club
    {
        $club = Club::query()->find($this->verify($code, self::CLUB));

        throw_unless($club, CheckInException::invalidCode());

        return $club;
    }

    /**
     * @throws CheckInException when the code is malformed, expired or unknown
     */
    public function memberFrom(string $code): User
    {
        $member = User::query()->find($this->verify($code, self::MEMBER));

        throw_unless($member, CheckInException::invalidCode());

        return $member;
    }

    private function mint(string $subject, int $id, int $window): string
    {
        $payload = $subject.$id.'.'.mb_strtoupper(base_convert((string) $window, 10, 36));

        return $payload.'.'.$this->signature($payload);
    }

    /**
     * Verify a code by re-minting it for every accepted window: that way the
     * payload can only ever be read back in exactly the form it was signed.
     *
     * @throws CheckInException
     */
    private function verify(string $code, string $subject): int
    {
        $code = mb_strtoupper(trim($code));

        throw_unless(preg_match('/^'.$subject.'([1-9]\d*)\./', $code, $matches) === 1, CheckInException::invalidCode());

        $id = (int) $matches[1];
        $window = $this->currentWindow();

        for ($offset = 0; $offset <= self::GRACE_WINDOWS; $offset++) {
            if (hash_equals($this->mint($subject, $id, $window - $offset), $code)) {
                return $id;
            }
        }

        throw CheckInException::invalidCode();
    }

    private function signature(string $payload): string
    {
        return mb_strtoupper(mb_substr(
            hash_hmac('sha256', $payload, (string) config('app.key')),
            0,
            self::SIGNATURE_LENGTH,
        ));
    }

    private function currentWindow(): int
    {
        return intdiv(now()->getTimestamp(), self::PERIOD_SECONDS);
    }
}
