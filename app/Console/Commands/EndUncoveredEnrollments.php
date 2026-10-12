<?php

namespace App\Console\Commands;

use App\Actions\Lessons\CancelEnrollment;
use App\Enums\EnrollmentType;
use App\Enums\SubscriptionStatus;
use App\Models\LessonEnrollment;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class EndUncoveredEnrollments extends Command
{
    protected $signature = 'enrollments:end-uncovered';

    protected $description = 'End recurring enrollments of members whose subscription is no longer current, releasing their spots';

    public function handle(CancelEnrollment $cancelEnrollment): int
    {
        $ended = 0;
        $failed = 0;

        $this->uncoveredEnrollments()->chunkById(100, function ($enrollments) use ($cancelEnrollment, &$ended, &$failed) {
            foreach ($enrollments as $enrollment) {
                try {
                    $cancelEnrollment->handle($enrollment);
                    $ended++;
                } catch (Throwable $exception) {
                    $failed++;

                    report($exception);
                }
            }
        });

        $this->info("Ended {$ended} recurring enrollments".($failed > 0 ? ", {$failed} failed." : '.'));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Recurring enrollments that were paid for by a subscription, for members
     * who have no current subscription in that club any more. Members who
     * never had one keep their spot and pay per visit with a strip card.
     *
     * @return \Illuminate\Database\Eloquent\Builder<LessonEnrollment>
     */
    private function uncoveredEnrollments()
    {
        return LessonEnrollment::query()
            ->where('type', EnrollmentType::Fixed)
            ->whereNotNull('subscription_id')
            ->whereNotExists(fn (Builder $query) => $query
                ->select(DB::raw(1))
                ->from('subscriptions')
                ->whereColumn('subscriptions.club_id', 'lesson_enrollments.club_id')
                ->whereColumn('subscriptions.user_id', 'lesson_enrollments.user_id')
                ->where(fn (Builder $current) => $current
                    ->where('subscriptions.status', SubscriptionStatus::Active->value)
                    ->orWhere(fn (Builder $cancelled) => $cancelled
                        ->where('subscriptions.status', SubscriptionStatus::Cancelled->value)
                        ->whereDate('subscriptions.ends_at', '>=', today())
                    )
                )
            );
    }
}
