<?php

namespace App\Actions\Lessons;

use App\Enums\LessonOccurrenceStatus;
use App\Models\LessonEnrollment;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use Illuminate\Support\Facades\DB;

class CancelEnrollment
{
    public function __construct(protected CreditLedger $ledger) {}

    /**
     * Cancel an enrollment and release its future spots. Credits come back for
     * every released lesson, except when the member cancels a lesson themselves
     * after the club's cancellation deadline: that credit stays spent.
     *
     * @param  User|null  $actor  who cancels; staff and the system (null) always refund
     * @return array{refunded: int, forfeited: int}
     */
    public function handle(LessonEnrollment $enrollment, ?User $actor = null): array
    {
        $appliesDeadline = $actor !== null && $actor->id === $enrollment->user_id;

        return DB::transaction(function () use ($enrollment, $actor, $appliesDeadline): array {
            $refundableSpots = 0;
            $lateSpots = 0;
            $occurrence = null;

            if ($enrollment->isFixed()) {
                $absentOccurrenceIds = $enrollment->absences()->pluck('lesson_occurrence_id');

                $futureOccurrences = $enrollment->lesson->occurrences()
                    ->where('starts_at', '>=', now())
                    ->where('status', LessonOccurrenceStatus::Scheduled)
                    ->whereNotIn('id', $absentOccurrenceIds)
                    ->get();

                foreach ($futureOccurrences as $futureOccurrence) {
                    $futureOccurrence->setRelation('club', $enrollment->club);
                    $futureOccurrence->releaseSpot();

                    if ($appliesDeadline && ! $futureOccurrence->isFreeToCancel()) {
                        $lateSpots++;
                    } else {
                        $refundableSpots++;
                    }
                }

                $enrollment->absences()->delete();
            } elseif ($enrollment->occurrence && $enrollment->occurrence->starts_at->isFuture()) {
                $occurrence = $enrollment->occurrence;
                $occurrence->releaseSpot();

                if ($appliesDeadline && ! $occurrence->isFreeToCancel()) {
                    $lateSpots = 1;
                } else {
                    $refundableSpots = 1;
                }
            }

            $toRefund = min($enrollment->credits_used, $refundableSpots);
            $toForfeit = min($enrollment->credits_used - $toRefund, $lateSpots);

            $refunded = $this->ledger->refund($enrollment, $toRefund, $occurrence, $actor);
            $this->ledger->forfeit($enrollment, $toForfeit, $occurrence, $actor);

            $enrollment->delete();

            return ['refunded' => $refunded, 'forfeited' => $toForfeit];
        });
    }
}
