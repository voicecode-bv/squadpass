<?php

namespace App\Actions\Lessons;

use App\Actions\Payments\ResolveMollieCustomer;
use App\Enums\EnrollmentType;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\TrialLessonException;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\User;
use App\Services\Mollie\MollieGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Books a trial lesson for a visitor from the public trial page. The visitor gets
 * a light account with a Trial membership, which does not count towards the
 * member limit until staff convert it through Club::addMember().
 */
class BookTrialLesson
{
    public function __construct(
        protected MollieGateway $gateway,
        protected ResolveMollieCustomer $resolveMollieCustomer,
        protected ReleaseTrialLesson $releaseTrialLesson,
        protected ConfirmTrialLesson $confirmTrialLesson,
    ) {}

    /**
     * @param  array{first_name: string, name_prefix?: string|null, last_name: string, email: string, phone?: string|null}  $person
     * @return string|null the Mollie checkout URL for a paid trial; null once a free trial is booked
     *
     * @throws TrialLessonException
     */
    public function handle(LessonOccurrence $occurrence, array $person): ?string
    {
        $occurrence->loadMissing(['club', 'lesson']);
        $club = $occurrence->club;
        $lesson = $occurrence->lesson;

        $bookable = $club->trial_lessons_enabled
            && $lesson->trial_enabled
            && ! $occurrence->isCancelled()
            && $occurrence->starts_at->isFuture()
            && (! $lesson->hasPaidTrial() || $club->acceptsOnlinePayments());

        throw_unless($bookable, TrialLessonException::unavailable());

        $email = Str::lower(trim($person['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            throw_if($user->isStaffOf($club), TrialLessonException::alreadyKnown());

            $existing = Membership::query()->where('club_id', $club->id)->where('user_id', $user->id)->first();

            throw_if($existing?->status === MembershipStatus::Active, TrialLessonException::alreadyMember());
            throw_if($existing !== null, TrialLessonException::alreadyKnown());
        }

        $accountCreated = $user === null;

        $enrollment = DB::transaction(function () use ($occurrence, $club, $person, $email, $user): LessonEnrollment {
            // A random password nobody knows: the visitor sets their own one via the reset flow.
            $user ??= User::query()->create([
                'first_name' => $person['first_name'],
                'name_prefix' => $person['name_prefix'] ?? null,
                'last_name' => $person['last_name'],
                'email' => $email,
                'password' => Str::random(64),
            ]);

            Membership::query()->create([
                'club_id' => $club->id,
                'user_id' => $user->id,
                'membership_number' => $club->allocateMembershipNumber(),
                'status' => MembershipStatus::Trial,
                'joined_at' => now()->toDateString(),
                'trial_started_at' => now()->toDateString(),
                'phone' => filled($person['phone'] ?? null) ? $person['phone'] : null,
            ]);

            throw_unless($occurrence->claimSpot(), TrialLessonException::full());

            return LessonEnrollment::query()->create([
                'club_id' => $club->id,
                'user_id' => $user->id,
                'type' => EnrollmentType::Single,
                'lesson_occurrence_id' => $occurrence->id,
                'credits_used' => 0,
            ]);
        });

        if (! $lesson->hasPaidTrial()) {
            $this->confirmTrialLesson->handle($enrollment, $accountCreated);

            return null;
        }

        try {
            return $this->startPayment($enrollment, $occurrence, $accountCreated);
        } catch (Throwable $exception) {
            // No checkout means no booking: free the spot so the visitor can try again.
            $this->releaseTrialLesson->handle($enrollment);

            throw $exception;
        }
    }

    protected function startPayment(LessonEnrollment $enrollment, LessonOccurrence $occurrence, bool $accountCreated): string
    {
        $club = $occurrence->club;
        $lesson = $occurrence->lesson;
        $user = $enrollment->user;

        $result = $this->gateway->createPayment(
            account: $club,
            amountCents: $lesson->trial_price_cents,
            description: "{$club->name} — ".__('Trial lesson').": {$lesson->name}",
            redirectUrl: route('clubs.trial', ['club' => $club, 'paid' => 1]),
            webhookUrl: route('webhooks.mollie', $club),
            customerId: $this->resolveMollieCustomer->handle($club, $user),
            metadata: [
                'purpose' => 'trial_lesson',
                'lesson_enrollment_id' => $enrollment->id,
                'user_id' => $user->id,
            ],
        );

        Payment::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'payable_type' => LessonEnrollment::class,
            'payable_id' => $enrollment->id,
            'mollie_payment_id' => $result->id,
            'status' => PaymentStatus::Open,
            'amount_cents' => $lesson->trial_price_cents,
            'currency' => $club->currency,
            'metadata' => ['purpose' => 'trial_lesson', 'account_created' => $accountCreated],
        ]);

        return $result->checkoutUrl ?? route('clubs.trial', $club);
    }
}
