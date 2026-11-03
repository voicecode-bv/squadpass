<?php

namespace App\Actions\Lessons;

use App\Exceptions\EnrollmentException;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use Illuminate\Support\Facades\DB;

class MarkAbsence
{
    public function __construct(protected CreditLedger $ledger) {}

    /**
     * Skip one lesson of a recurring enrollment. The credit comes back, unless
     * the member reports absent themselves after the cancellation deadline.
     *
     * @param  User|null  $actor  who reports the absence; staff and the system (null) always refund
     * @return array{refunded: int, forfeited: int}
     */
    public function handle(LessonEnrollment $enrollment, LessonOccurrence $occurrence, ?User $actor = null): array
    {
        throw_unless($enrollment->isFixed(), EnrollmentException::notBookable());
        throw_unless($occurrence->lesson_id === $enrollment->lesson_id, EnrollmentException::notBookable());
        throw_if($occurrence->starts_at->isPast(), EnrollmentException::notBookable());

        $isLate = $actor !== null && $actor->id === $enrollment->user_id && ! $occurrence->isFreeToCancel();

        return DB::transaction(function () use ($enrollment, $occurrence, $actor, $isLate): array {
            $absence = $enrollment->absences()->firstOrCreate([
                'lesson_occurrence_id' => $occurrence->id,
            ]);

            if (! $absence->wasRecentlyCreated) {
                return ['refunded' => 0, 'forfeited' => 0];
            }

            $occurrence->releaseSpot();

            if ($enrollment->credits_used === 0) {
                return ['refunded' => 0, 'forfeited' => 0];
            }

            if ($isLate) {
                $absence->update(['credit_forfeited' => true]);
                $this->ledger->forfeit($enrollment, 1, $occurrence, $actor);

                return ['refunded' => 0, 'forfeited' => 1];
            }

            $enrollment->decrement('credits_used');

            return ['refunded' => $this->ledger->refund($enrollment, 1, $occurrence, $actor), 'forfeited' => 0];
        });
    }
}
