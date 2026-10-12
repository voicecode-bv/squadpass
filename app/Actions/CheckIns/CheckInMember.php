<?php

namespace App\Actions\CheckIns;

use App\Exceptions\CheckInException;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\StripCard;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckInMember
{
    /**
     * Check a member in. Coverage order:
     * 1. a reservation for the given lesson occurrence, but only while the
     *    subscription that paid the credit for it is still current,
     * 2. an unlimited subscription,
     * 3. one strip from the oldest usable strip card (FIFO by expiry).
     *
     * A reservation on its own is never free: without a current subscription
     * the visit falls through to a strip, so an ended or overdue subscription
     * cannot keep paying for lessons.
     */
    public function handle(User $member, Club $club, ?LessonOccurrence $occurrence = null, ?User $checkedInBy = null): CheckIn
    {
        throw_unless($member->isMemberOf($club), CheckInException::membershipRequired());

        if ($occurrence) {
            throw_unless($occurrence->club_id === $club->id, CheckInException::noCoverage());

            $alreadyCheckedIn = CheckIn::query()
                ->where('user_id', $member->id)
                ->where('lesson_occurrence_id', $occurrence->id)
                ->exists();

            throw_if($alreadyCheckedIn, CheckInException::alreadyCheckedIn());
        }

        return DB::transaction(function () use ($member, $club, $occurrence, $checkedInBy): CheckIn {
            $attributes = [
                'club_id' => $club->id,
                'user_id' => $member->id,
                'lesson_occurrence_id' => $occurrence?->id,
                'checked_in_by' => $checkedInBy?->id,
                'checked_in_at' => now(),
            ];

            $subscription = Subscription::currentFor($club->id, $member->id);

            // 1. An existing reservation covers the visit; the credit was spent at booking time.
            if ($subscription && $occurrence && $this->isEnrolledFor($member, $occurrence)) {
                return CheckIn::create($attributes);
            }

            // 2. An unlimited subscription covers any visit.
            if ($subscription?->isUnlimited()) {
                return CheckIn::create($attributes + ['subscription_id' => $subscription->id]);
            }

            // 3. Otherwise redeem one strip, oldest expiry first.
            $cards = StripCard::query()
                ->where('club_id', $club->id)
                ->where('user_id', $member->id)
                ->where('strips_remaining', '>', 0)
                ->orderByRaw('expires_at is null, expires_at asc')
                ->get();

            foreach ($cards as $card) {
                if ($card->redeemStrip()) {
                    return CheckIn::create($attributes + ['strip_card_id' => $card->id]);
                }
            }

            throw CheckInException::noCoverage();
        });
    }

    protected function isEnrolledFor(User $member, LessonOccurrence $occurrence): bool
    {
        $single = LessonEnrollment::query()
            ->where('lesson_occurrence_id', $occurrence->id)
            ->where('user_id', $member->id)
            ->exists();

        if ($single) {
            return true;
        }

        return LessonEnrollment::query()
            ->where('lesson_id', $occurrence->lesson_id)
            ->where('user_id', $member->id)
            ->whereDoesntHave('absences', fn ($query) => $query->where('lesson_occurrence_id', $occurrence->id))
            ->exists();
    }
}
