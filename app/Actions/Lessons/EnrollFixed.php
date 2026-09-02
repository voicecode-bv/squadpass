<?php

namespace App\Actions\Lessons;

use App\Enums\EnrollmentType;
use App\Enums\LessonOccurrenceStatus;
use App\Exceptions\EnrollmentException;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollFixed
{
    public function handle(User $user, Lesson $lesson): LessonEnrollment
    {
        throw_unless($user->isMemberOf($lesson->club), EnrollmentException::membershipRequired());
        throw_unless($lesson->active, EnrollmentException::notBookable());

        $alreadyFixed = LessonEnrollment::query()
            ->where('lesson_id', $lesson->id)
            ->where('user_id', $user->id)
            ->exists();

        throw_if($alreadyFixed, EnrollmentException::alreadyEnrolled());

        $subscription = Subscription::currentFor($lesson->club_id, $user->id);
        $usesCredits = $subscription !== null && ! $subscription->isUnlimited();

        return DB::transaction(function () use ($user, $lesson, $subscription, $usesCredits): LessonEnrollment {
            $occurrences = $lesson->occurrences()
                ->where('starts_at', '>=', now())
                ->where('status', LessonOccurrenceStatus::Scheduled)
                ->orderBy('starts_at')
                ->get();

            $newlyClaimed = 0;
            $transferredCredits = 0;

            foreach ($occurrences as $occurrence) {
                $existingSingle = LessonEnrollment::query()
                    ->where('lesson_occurrence_id', $occurrence->id)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existingSingle) {
                    // The single enrollment's claimed spot and spent credit transfer to the fixed enrollment.
                    $transferredCredits += $existingSingle->credits_used;
                    $existingSingle->delete();

                    continue;
                }

                // Rolling back the transaction reverts every claimed spot.
                throw_unless($occurrence->claimSpot(), EnrollmentException::full($occurrence));

                $newlyClaimed++;
            }

            if ($usesCredits) {
                throw_unless($subscription->consumeCredits($newlyClaimed), EnrollmentException::noCredits());
            }

            return LessonEnrollment::create([
                'club_id' => $lesson->club_id,
                'user_id' => $user->id,
                'type' => EnrollmentType::Fixed,
                'lesson_id' => $lesson->id,
                'subscription_id' => $subscription?->id,
                'credits_used' => ($usesCredits ? $newlyClaimed : 0) + $transferredCredits,
            ]);
        });
    }
}
