<?php

namespace App\Actions\Lessons;

use App\Enums\StaffPermission;
use App\Models\LessonEnrollment;
use App\Models\User;
use App\Notifications\NewTrialLead;
use App\Notifications\TrialLessonBooked;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the confirmation to the visitor and tells the club's staff about the new lead,
 * once a trial lesson is booked (free) or paid.
 */
class ConfirmTrialLesson
{
    public function handle(LessonEnrollment $enrollment, bool $accountCreated): void
    {
        $enrollment->loadMissing(['user', 'occurrence.club', 'occurrence.lesson']);
        $club = $enrollment->occurrence->club;

        $enrollment->user->notify((new TrialLessonBooked($enrollment, $accountCreated))->afterCommit());

        $staff = $club->staff()
            ->get()
            // Owners have every permission, so they are always included.
            ->filter(fn (User $member) => $member->hasStaffPermission($club, StaffPermission::Members));

        Notification::send($staff, (new NewTrialLead($enrollment))->afterCommit());
    }
}
