<?php

namespace App\Actions\MemberImports;

use App\Enums\MemberImportRowResult;
use App\Exceptions\ClubPlanException;
use App\Exceptions\InvalidMemberImportRow;
use App\Mail\ImportedMemberMail;
use App\Models\Club;
use App\Models\Membership;
use App\Models\User;
use App\Services\PersonName;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ImportMemberRow
{
    /**
     * Date formats accepted in a member list, Dutch day-first notation first.
     *
     * @var list<string>
     */
    protected const array DATE_FORMATS = ['!d-m-Y', '!j-n-Y', '!d/m/Y', '!j/n/Y', '!d.m.Y', '!Y-m-d', '!Y/m/d'];

    /**
     * Add one person from a member list to the club. Someone without an
     * account gets one (they set a password via the invitation or "forgot
     * password"); an existing account is linked. The membership goes through
     * Club::addMember(), so the member limit of the club's plan applies.
     *
     * @param  array<string, string>  $values  import field => raw cell value
     *
     * @throws InvalidMemberImportRow|ClubPlanException
     */
    public function handle(Club $club, array $values, bool $sendInvitation): MemberImportRowResult
    {
        $email = Str::lower(trim($values['email'] ?? ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
            throw InvalidMemberImportRow::because(__('":email" is not a valid email address.', ['email' => $email]));
        }

        $nameParts = $this->nameParts($values);
        $details = $this->membershipDetails($club, $values);

        $user = User::query()->where('email', $email)->first();

        if ($user?->isMemberOf($club)) {
            return MemberImportRowResult::Skipped;
        }

        $isNewAccount = $user === null;

        $membership = DB::transaction(function () use ($club, $user, $email, $nameParts, $details): Membership {
            // A random password nobody knows: the member sets their own one via the reset flow.
            $user ??= User::query()->create([...$nameParts, 'email' => $email, 'password' => Str::random(64)]);

            $membership = $club->addMember($user);

            // A returning member keeps their number; a new one takes the number from the list.
            if (! $membership->wasRecentlyCreated) {
                unset($details['membership_number']);
            }

            if ($details !== []) {
                $membership->update($details);
            }

            return $membership;
        });

        if ($sendInvitation) {
            Mail::to($email)->queue(new ImportedMemberMail($membership, $isNewAccount));
        }

        return $isNewAccount ? MemberImportRowResult::Created : MemberImportRowResult::Linked;
    }

    /**
     * First name, prefix and last name from separate columns, or split from a
     * full name column when the list has no separate first and last name.
     *
     * @param  array<string, string>  $values
     * @return array{first_name: string, name_prefix: string|null, last_name: string}
     */
    protected function nameParts(array $values): array
    {
        $firstName = trim($values['first_name'] ?? '');
        $lastName = trim($values['last_name'] ?? '');

        $parts = $firstName !== '' || $lastName !== ''
            ? ['first_name' => $firstName, 'name_prefix' => trim($values['name_prefix'] ?? '') ?: null, 'last_name' => $lastName]
            : PersonName::split($values['name'] ?? '');

        if ($parts['first_name'] === '' && $parts['last_name'] === '') {
            throw InvalidMemberImportRow::because(__('The name is missing.'));
        }

        return [
            'first_name' => mb_substr($parts['first_name'], 0, 100),
            'name_prefix' => $parts['name_prefix'] !== null ? mb_substr($parts['name_prefix'], 0, 50) : null,
            'last_name' => mb_substr($parts['last_name'], 0, 100),
        ];
    }

    /**
     * Optional membership fields, validated. Only filled values are returned.
     *
     * @param  array<string, string>  $values
     * @return array<string, mixed>
     */
    protected function membershipDetails(Club $club, array $values): array
    {
        $details = [];

        $number = trim($values['membership_number'] ?? '');

        if ($number !== '') {
            if (! ctype_digit($number) || (int) $number < 1 || (int) $number > 4294967295) {
                throw InvalidMemberImportRow::because(__('":value" is not a valid membership number.', ['value' => $number]));
            }

            $taken = Membership::query()->where('club_id', $club->id)->where('membership_number', (int) $number)->exists();

            if ($taken) {
                throw InvalidMemberImportRow::because(__('Membership number :number is already in use.', ['number' => $number]));
            }

            $details['membership_number'] = (int) $number;
        }

        foreach (['joined_at', 'date_of_birth'] as $field) {
            $value = trim($values[$field] ?? '');

            if ($value !== '') {
                $date = $this->parseDate($value);

                if ($date === null || $date->isFuture()) {
                    throw InvalidMemberImportRow::because(__('":value" is not a valid date (use for example 31-12-1990).', ['value' => $value]));
                }

                $details[$field] = $date->toDateString();
            }
        }

        $phone = trim($values['phone'] ?? '');

        if ($phone !== '') {
            $details['phone'] = mb_substr($phone, 0, 50);
        }

        return $details;
    }

    protected function parseDate(string $value): ?CarbonImmutable
    {
        foreach (self::DATE_FORMATS as $format) {
            if (! CarbonImmutable::canBeCreatedFromFormat($value, $format)) {
                continue;
            }

            $date = CarbonImmutable::createFromFormat($format, $value);

            // Reject overflowing dates such as 31-02-1990 that PHP silently rolls over.
            if ($date->format(ltrim($format, '!')) === $value) {
                return $date;
            }
        }

        return null;
    }
}
