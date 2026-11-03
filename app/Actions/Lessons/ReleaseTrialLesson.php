<?php

namespace App\Actions\Lessons;

use App\Enums\MembershipStatus;
use App\Models\LessonEnrollment;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a trial booking that was never paid: frees the spot and removes the
 * trial membership, so the visitor can book again.
 */
class ReleaseTrialLesson
{
    public function handle(LessonEnrollment $enrollment): void
    {
        DB::transaction(function () use ($enrollment): void {
            $deleted = LessonEnrollment::query()->whereKey($enrollment->id)->delete() === 1;

            if ($deleted) {
                $enrollment->occurrence?->releaseSpot();
            }

            Membership::query()
                ->where('club_id', $enrollment->club_id)
                ->where('user_id', $enrollment->user_id)
                ->where('status', MembershipStatus::Trial)
                ->delete();
        });
    }
}
