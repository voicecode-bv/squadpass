<?php

namespace App\Actions\ClubSubscriptions;

use App\Actions\Addons\ResolvePlatformMollieCustomer;
use App\Enums\ClubPlan;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\ClubPlanException;
use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\ClubSubscriptionPayment;
use App\Models\User;
use App\Services\Mollie\MollieGateway;
use App\Services\Mollie\PlatformMollieAccount;

class StartClubSubscription
{
    public function __construct(
        protected MollieGateway $gateway,
        protected PlatformMollieAccount $platform,
        protected ResolvePlatformMollieCustomer $resolveCustomer,
    ) {}

    /**
     * Start a SquadPass plan for a club. The first payment on the platform Mollie
     * account establishes the mandate and covers the first month. Returns the
     * checkout URL.
     *
     * @throws ClubPlanException
     */
    public function handle(Club $club, ClubPlan $plan, User $purchaser): string
    {
        throw_unless($plan->isSelfService(), ClubPlanException::notSelfService());
        throw_unless($this->platform->isConfigured(), ClubPlanException::paymentsUnavailable());

        $blocking = ClubSubscription::query()
            ->where('club_id', $club->id)
            ->whereIn('status', [SubscriptionStatus::Pending, SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Cancelled])
            ->get()
            ->first(fn (ClubSubscription $subscription) => $subscription->blocksNewSubscription());

        throw_if($blocking, ClubPlanException::alreadySubscribed());

        $limit = $plan->memberLimit();

        throw_if($limit !== null && $club->activeMemberCount() > $limit, ClubPlanException::tooManyMembersForPlan($limit ?? 0));

        $customerId = $this->resolveCustomer->handle($club, $purchaser->email);
        $priceCents = (int) $plan->priceCents();

        $subscription = ClubSubscription::create([
            'club_id' => $club->id,
            'user_id' => $purchaser->id,
            'plan' => $plan,
            'status' => SubscriptionStatus::Pending,
            'price_cents' => $priceCents,
            'starts_at' => today(),
        ]);

        $result = $this->gateway->createPayment(
            account: $this->platform,
            amountCents: $priceCents,
            description: "SquadPass {$plan->label()} — {$club->name}",
            redirectUrl: route('clubs.plan', $club),
            webhookUrl: route('webhooks.mollie.platform'),
            customerId: $customerId,
            sequenceType: 'first',
            metadata: [
                'purpose' => 'club_subscription_first',
                'club_subscription_id' => $subscription->id,
                'club_id' => $club->id,
            ],
        );

        ClubSubscriptionPayment::create([
            'club_id' => $club->id,
            'club_subscription_id' => $subscription->id,
            'mollie_payment_id' => $result->id,
            'purpose' => ClubSubscriptionPayment::PURPOSE_FIRST,
            'status' => PaymentStatus::Open,
            'amount_cents' => $priceCents,
            'currency' => $this->platform->mollieCurrency(),
        ]);

        return $result->checkoutUrl ?? route('clubs.plan', $club);
    }
}
