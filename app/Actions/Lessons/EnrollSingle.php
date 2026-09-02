<?php

namespace App\Actions\Lessons;

use App\Enums\EnrollmentType;
use App\Exceptions\EnrollmentException;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollSingle
{
    public function handle(User $user, LessonOccurrence $occurrence): LessonEnrollment
    {
        throw_unless($user->isMemberOf($occurrence->club), EnrollmentException::membershipRequired());
        throw_if($occurrence->isCancelled() || $occurrence->starts_at->isPast(), EnrollmentException::notBookable());

        $alreadySingle = LessonEnrollment::query()
            ->where('lesson_occurrence_id', $occurrence->id)
            ->where('user_id', $user->id)
            ->exists();

        throw_if($alreadySingle, EnrollmentException::alreadyEnrolled());

        $subscription = Subscription::currentFor($occurrence->club_id, $user->id);
        $usesCredits = $subscription !== null && ! $subscription->isUnlimited();

        $fixed = LessonEnrollment::query()
            ->where('lesson_id', $occurrence->lesson_id)
            ->where('user_id', $user->id)
            ->first();

        if ($fixed) {
            $absence = $fixed->absences()->where('lesson_occurrence_id', $occurrence->id)->first();

            throw_unless($absence, EnrollmentException::alreadyEnrolled());

            // Rejoining after an absence reclaims the spot (and a credit) on the fixed enrollment.
            return DB::transaction(function () use ($occurrence, $fixed, $absence, $subscription, $usesCredits): LessonEnrollment {
                throw_unless($occurrence->claimSpot(), EnrollmentException::full($occurrence));

                if ($usesCredits) {
                    throw_unless($subscription->consumeCredits(1), EnrollmentException::noCredits());

                    $fixed->increment('credits_used');
                }

                $absence->delete();

                return $fixed;
            });
        }

        return DB::transaction(function () use ($occurrence, $user, $subscription, $usesCredits): LessonEnrollment {
            throw_unless($occurrence->claimSpot(), EnrollmentException::full($occurrence));

            if ($usesCredits) {
                throw_unless($subscription->consumeCredits(1), EnrollmentException::noCredits());
            }

            return LessonEnrollment::create([
                'club_id' => $occurrence->club_id,
                'user_id' => $user->id,
                'type' => EnrollmentType::Single,
                'lesson_occurrence_id' => $occurrence->id,
                'subscription_id' => $subscription?->id,
                'credits_used' => $usesCredits ? 1 : 0,
            ]);
        });
    }
}
