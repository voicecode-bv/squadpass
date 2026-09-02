<?php

namespace Database\Seeders;

use App\Actions\Lessons\EnrollSingle;
use App\Enums\AddonKey;
use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Exceptions\EnrollmentException;
use App\Models\Addon;
use App\Models\AddonSubscription;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonOccurrence;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Room;
use App\Models\StripCard;
use App\Models\StripCardPlan;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\Trainer;
use App\Models\User;
use App\Services\Lessons\OccurrenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Seed three demo clubs with staff, trainers, rooms, lessons, members with
 * memberships spread over the past year, subscriptions, strip cards, paid
 * payments and check-in history, so every dashboard and chart has data.
 *
 * Demo accounts (password for all: "password"):
 * - superadmin@example.com (superadmin)
 * - owner@example.com (owner of all three clubs)
 * - trainer@example.com (trainer at Padel Club Amsterdam)
 * - lid1@example.com (member of Padel Club Amsterdam, for the member portal)
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Mollie test-mode API key used for all demo clubs.
     */
    private const string MOLLIE_TEST_KEY = 'test_3A4C2ubRV62Dd7f9WM4dwUAr6KmJbq';

    private int $paymentSequence = 0;

    public function run(): void
    {
        User::factory()->superadmin()->create([
            'name' => 'Superadmin',
            'email' => 'superadmin@example.com',
        ]);

        $owner = User::factory()->create([
            'name' => 'Demo Eigenaar',
            'email' => 'owner@example.com',
        ]);

        $this->seedAddons();

        foreach ($this->clubConfigurations() as $index => $configuration) {
            $this->seedClub($configuration, $owner, isPrimary: $index === 0);
        }
    }

    /**
     * Price the sellable add-ons so the superadmin page and club add-on page have data.
     */
    protected function seedAddons(): void
    {
        $prices = [
            AddonKey::Mollie->value => 1500,
            AddonKey::Moneybird->value => 1000,
        ];

        foreach (Addon::syncFromKeys() as $addon) {
            $addon->update(['price_cents' => $prices[$addon->key->value] ?? 1000, 'is_active' => true]);
        }
    }

    /**
     * @return list<array{name: string, slug: string, members: int, rooms: list<string>, monthly_price_cents: int, lessons: list<array{name: string, day_of_week: int, start_time: string, capacity: int}>}>
     */
    protected function clubConfigurations(): array
    {
        return [
            [
                'name' => 'Padel Club Amsterdam',
                'slug' => 'padel-club-amsterdam',
                'members' => 85,
                'rooms' => ['Baan 1', 'Baan 2', 'Baan 3', 'Baan 4'],
                'monthly_price_cents' => 6900,
                'lessons' => [
                    ['name' => 'Padel beginners', 'day_of_week' => 1, 'start_time' => '19:00:00', 'capacity' => 8],
                    ['name' => 'Padel gevorderden', 'day_of_week' => 2, 'start_time' => '20:00:00', 'capacity' => 8],
                    ['name' => 'Padel clinic', 'day_of_week' => 3, 'start_time' => '19:30:00', 'capacity' => 12],
                    ['name' => 'Junioren padel', 'day_of_week' => 4, 'start_time' => '16:30:00', 'capacity' => 10],
                    ['name' => 'Damespadel', 'day_of_week' => 5, 'start_time' => '09:30:00', 'capacity' => 8],
                    ['name' => 'Competitietraining', 'day_of_week' => 6, 'start_time' => '10:00:00', 'capacity' => 12],
                ],
            ],
            [
                'name' => 'SquashPoint Utrecht',
                'slug' => 'squashpoint-utrecht',
                'members' => 60,
                'rooms' => ['Baan 1', 'Baan 2', 'Baan 3'],
                'monthly_price_cents' => 5900,
                'lessons' => [
                    ['name' => 'Squash beginners', 'day_of_week' => 1, 'start_time' => '18:30:00', 'capacity' => 8],
                    ['name' => 'Squash gevorderden', 'day_of_week' => 2, 'start_time' => '19:30:00', 'capacity' => 8],
                    ['name' => 'Squash drills', 'day_of_week' => 3, 'start_time' => '20:00:00', 'capacity' => 10],
                    ['name' => 'Junioren squash', 'day_of_week' => 4, 'start_time' => '16:00:00', 'capacity' => 10],
                    ['name' => 'Conditietraining', 'day_of_week' => 6, 'start_time' => '09:00:00', 'capacity' => 14],
                    ['name' => 'Interne competitie', 'day_of_week' => 7, 'start_time' => '11:00:00', 'capacity' => 12],
                ],
            ],
            [
                'name' => 'FitFabriek Rotterdam',
                'slug' => 'fitfabriek-rotterdam',
                'members' => 45,
                'rooms' => ['Zaal A', 'Zaal B', 'Spinningzaal'],
                'monthly_price_cents' => 4900,
                'lessons' => [
                    ['name' => 'HIIT', 'day_of_week' => 1, 'start_time' => '07:00:00', 'capacity' => 14],
                    ['name' => 'Spinning', 'day_of_week' => 2, 'start_time' => '19:00:00', 'capacity' => 16],
                    ['name' => 'Bootcamp', 'day_of_week' => 3, 'start_time' => '18:30:00', 'capacity' => 14],
                    ['name' => 'Yoga flow', 'day_of_week' => 4, 'start_time' => '08:00:00', 'capacity' => 12],
                    ['name' => 'Krachttraining', 'day_of_week' => 5, 'start_time' => '19:30:00', 'capacity' => 10],
                    ['name' => 'Circuittraining', 'day_of_week' => 6, 'start_time' => '10:30:00', 'capacity' => 14],
                ],
            ],
        ];
    }

    /**
     * @param  array{name: string, slug: string, members: int, rooms: list<string>, monthly_price_cents: int, lessons: list<array{name: string, day_of_week: int, start_time: string, capacity: int}>}  $configuration
     */
    protected function seedClub(array $configuration, User $owner, bool $isPrimary): void
    {
        $club = Club::create([
            'name' => $configuration['name'],
            'slug' => $configuration['slug'],
            'mollie_api_key' => self::MOLLIE_TEST_KEY,
            'onboarded_at' => now()->subMonths(14),
        ]);

        $club->addStaff($owner, StaffRole::Owner);

        // Every demo club has the Mollie add-on, so member payments work out of the box.
        AddonSubscription::factory()->active()->create([
            'club_id' => $club->id,
            'addon_id' => Addon::query()->where('key', AddonKey::Mollie->value)->firstOrFail()->id,
            'user_id' => $owner->id,
            'price_cents' => 1500,
            'starts_at' => now()->subMonths(6)->toDateString(),
        ]);

        $rooms = collect($configuration['rooms'])
            ->map(fn (string $name) => Room::create(['club_id' => $club->id, 'name' => $name]));

        $trainers = $this->seedTrainers($club, $isPrimary);
        [$unlimitedPlan, $basicPlan, $stripCardPlan] = $this->seedPlans($club, $configuration['monthly_price_cents']);

        $activeMembers = $this->seedMembers($club, $configuration['members'], $isPrimary);
        $this->seedCoverageAndHistory($club, $activeMembers, $unlimitedPlan, $basicPlan, $stripCardPlan, $isPrimary);

        $this->seedLessons($club, $configuration['lessons'], $rooms, $trainers);
        $this->seedEnrollments($club, $activeMembers);
        $this->seedTickets($club, $activeMembers, $owner);
    }

    /**
     * A handful of support tickets per club, in different stages of handling.
     *
     * @param  Collection<int, array{user: User, joined: CarbonImmutable}>  $activeMembers
     */
    protected function seedTickets(Club $club, Collection $activeMembers, User $owner): void
    {
        $definitions = [
            ['subject' => 'Vraag over mijn factuur', 'category' => TicketCategory::Billing],
            ['subject' => 'Dubbele afschrijving abonnement', 'category' => TicketCategory::Billing],
            ['subject' => 'Strippenkaart niet bijgewerkt na bezoek', 'category' => TicketCategory::Billing],
            ['subject' => 'Kan mijn vaste les niet annuleren', 'category' => TicketCategory::Lessons],
            ['subject' => 'Wanneer start het nieuwe seizoen?', 'category' => TicketCategory::Lessons],
            ['subject' => 'Wijziging van mijn e-mailadres', 'category' => TicketCategory::Other],
        ];

        foreach (fake()->randomElements($definitions, fake()->numberBetween(4, 6)) as $definition) {
            $member = $activeMembers->random()['user'];
            $status = fake()->randomElement(TicketStatus::cases());
            $createdAt = CarbonImmutable::instance(fake()->dateTimeBetween('-6 weeks', '-1 day'));
            $lastActivity = $createdAt;

            $ticket = Ticket::factory()->for($club)->create([
                'user_id' => $member->id,
                'assigned_to' => $status === TicketStatus::Open ? null : $owner->id,
                'subject' => $definition['subject'],
                'category' => $definition['category'],
                'status' => $status,
                'priority' => fake()->randomElement(TicketPriority::cases()),
                'created_at' => $createdAt,
            ]);

            TicketMessage::factory()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $member->id,
                'body' => fake()->paragraph(),
                'created_at' => $createdAt,
            ]);

            if ($status !== TicketStatus::Open) {
                $lastActivity = $createdAt->addDay();

                TicketMessage::factory()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $owner->id,
                    'body' => fake()->paragraph(),
                    'created_at' => $lastActivity,
                ]);
            }

            $ticket->timestamps = false;
            $ticket->forceFill(['updated_at' => $lastActivity])->save();
        }
    }

    /**
     * @return Collection<int, Trainer>
     */
    protected function seedTrainers(Club $club, bool $isPrimary): Collection
    {
        $users = User::factory(3)->create();

        if ($isPrimary) {
            $users->prepend(User::factory()->create([
                'name' => 'Demo Trainer',
                'email' => 'trainer@example.com',
            ]));
        }

        return $users->map(fn (User $user) => Trainer::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
        ]));
    }

    /**
     * @return array{SubscriptionPlan, SubscriptionPlan, StripCardPlan}
     */
    protected function seedPlans(Club $club, int $monthlyPriceCents): array
    {
        $unlimited = SubscriptionPlan::create([
            'club_id' => $club->id,
            'name' => 'Onbeperkt',
            'price_cents' => $monthlyPriceCents,
            'interval' => 'monthly',
        ]);

        $basic = SubscriptionPlan::create([
            'club_id' => $club->id,
            'name' => 'Basis (4 lessen)',
            'price_cents' => (int) round($monthlyPriceCents * 0.55, -2),
            'interval' => 'monthly',
            'credits' => 4,
        ]);

        $stripCard = StripCardPlan::create([
            'club_id' => $club->id,
            'name' => '10-strippenkaart',
            'strips' => 10,
            'price_cents' => (int) round($monthlyPriceCents * 1.4, -2),
            'validity_months' => 12,
        ]);

        return [$unlimited, $basic, $stripCard];
    }

    /**
     * Create members with join dates spread over the past 14 months (weighted
     * towards recent months, so the growth charts trend upwards) and a small
     * share of ended memberships.
     *
     * @return Collection<int, array{user: User, joined: CarbonImmutable}>
     */
    protected function seedMembers(Club $club, int $count, bool $isPrimary): Collection
    {
        $users = User::factory($count - ($isPrimary ? 1 : 0))->create();

        if ($isPrimary) {
            $users->prepend(User::factory()->create([
                'name' => 'Demo Lid',
                'email' => 'lid1@example.com',
            ]));
        }

        $activeMembers = collect();

        foreach ($users as $index => $user) {
            $isDemoMember = $isPrimary && $index === 0;
            $joined = $isDemoMember ? now()->subMonths(6)->startOfDay() : $this->randomJoinDate();
            $ended = (! $isDemoMember && fake()->boolean(8) && $joined->lt(now()->subMonth()))
                ? fake()->dateTimeBetween($joined, 'now')
                : null;

            Membership::create([
                'club_id' => $club->id,
                'user_id' => $user->id,
                'membership_number' => $club->allocateMembershipNumber(),
                'status' => $ended ? MembershipStatus::Ended : MembershipStatus::Active,
                'joined_at' => $joined->toDateString(),
                'ended_at' => $ended?->format('Y-m-d'),
            ]);

            if (! $ended) {
                $activeMembers->push(['user' => $user, 'joined' => $joined]);
            }
        }

        return $activeMembers;
    }

    /**
     * Give active members a subscription or strip card, generate the matching
     * paid payments per month and a realistic check-in history.
     *
     * @param  Collection<int, array{user: User, joined: CarbonImmutable}>  $activeMembers
     */
    protected function seedCoverageAndHistory(
        Club $club,
        Collection $activeMembers,
        SubscriptionPlan $unlimitedPlan,
        SubscriptionPlan $basicPlan,
        StripCardPlan $stripCardPlan,
        bool $isPrimary,
    ): void {
        $paymentRows = [];
        $checkInRows = [];

        foreach ($activeMembers as $index => $member) {
            $user = $member['user'];
            $joined = $member['joined'];
            $isDemoMember = $isPrimary && $index === 0;

            // Deterministic mix: ~45% subscription, ~35% strip card, ~20% none.
            $roll = $index % 20;
            $subscription = null;
            $stripCard = null;

            if ($isDemoMember || $roll <= 8) {
                $plan = ($isDemoMember || $roll <= 4) ? $unlimitedPlan : $basicPlan;
                // A few subscribers per club have a failed direct debit, so the
                // failed-charges insight on the club dashboard has demo data.
                $isPastDue = ! $isDemoMember && $roll === 8;

                $subscription = Subscription::create([
                    'club_id' => $club->id,
                    'user_id' => $user->id,
                    'subscription_plan_id' => $plan->id,
                    'status' => $isPastDue ? SubscriptionStatus::PastDue : SubscriptionStatus::Active,
                    'starts_at' => $joined,
                    'next_billing_at' => now()->addDays(fake()->numberBetween(1, 28)),
                    'credits_remaining' => $plan->credits,
                ]);

                foreach ($this->billedMonths($joined) as $month) {
                    $paymentRows[] = $this->paymentRow($club, $user, $plan->price_cents, $month->addDays($joined->day % 27));
                }

                if ($isPastDue) {
                    $paymentRows[] = array_merge(
                        $this->paymentRow($club, $user, $plan->price_cents, now()->subDays(fake()->numberBetween(2, 12))),
                        [
                            'status' => PaymentStatus::Failed->value,
                            'paid_at' => null,
                            'payable_type' => Subscription::class,
                            'payable_id' => $subscription->id,
                            'metadata' => json_encode(['purpose' => 'subscription_recurring']),
                        ],
                    );
                }
            }

            if ($isDemoMember || ($roll >= 9 && $roll <= 15)) {
                $purchased = CarbonImmutable::instance(fake()->dateTimeBetween(max($joined, now()->subMonths(10)), 'now'));
                $stripsUsed = fake()->numberBetween(0, min(9, (int) $purchased->diffInWeeks(now())));

                $stripCard = StripCard::create([
                    'club_id' => $club->id,
                    'user_id' => $user->id,
                    'strip_card_plan_id' => $stripCardPlan->id,
                    'strips_total' => $stripCardPlan->strips,
                    'strips_remaining' => $stripCardPlan->strips - $stripsUsed,
                    'purchased_at' => $purchased->toDateString(),
                    'expires_at' => $purchased->copy()->addMonths($stripCardPlan->validity_months)->toDateString(),
                ]);

                $paymentRows[] = $this->paymentRow($club, $user, $stripCardPlan->price_cents, $purchased);
            }

            array_push($checkInRows, ...$this->checkInRows($club, $user, $joined, $subscription, $stripCard));
        }

        foreach (array_chunk($paymentRows, 200) as $chunk) {
            Payment::insert($chunk);
        }

        foreach (array_chunk($checkInRows, 200) as $chunk) {
            CheckIn::insert($chunk);
        }
    }

    /**
     * Month starts to bill for a subscription, capped at the trailing year.
     *
     * @return list<CarbonImmutable>
     */
    protected function billedMonths(CarbonImmutable $joined): array
    {
        $month = $joined->startOfMonth()->max(now()->subMonths(11)->startOfMonth());
        $months = [];

        while ($month->lte(now()->startOfMonth())) {
            $months[] = $month;
            $month = $month->addMonth();
        }

        return $months;
    }

    /**
     * @return array<string, mixed>
     */
    protected function paymentRow(Club $club, User $user, int $amountCents, CarbonImmutable $paidAt): array
    {
        $paidAt = $paidAt->copy()->min(now())->setTime(fake()->numberBetween(8, 21), fake()->numberBetween(0, 59));

        return [
            'club_id' => $club->id,
            'user_id' => $user->id,
            'payable_type' => null,
            'payable_id' => null,
            'mollie_payment_id' => 'tr_demo_'.Str::padLeft((string) $this->paymentSequence++, 8, '0'),
            'status' => PaymentStatus::Paid->value,
            'amount_cents' => $amountCents,
            'currency' => 'EUR',
            'paid_at' => $paidAt->toDateTimeString(),
            'metadata' => null,
            'created_at' => $paidAt->toDateTimeString(),
            'updated_at' => $paidAt->toDateTimeString(),
        ];
    }

    /**
     * Check-ins over the past 13 weeks, at a member-specific weekly frequency.
     *
     * @return list<array<string, mixed>>
     */
    protected function checkInRows(Club $club, User $user, CarbonImmutable $joined, ?Subscription $subscription, ?StripCard $stripCard): array
    {
        $windowStart = $joined->copy()->max(now()->copy()->subWeeks(13));
        $weeks = (int) $windowStart->diffInWeeks(now());

        if ($weeks === 0) {
            return [];
        }

        $visits = (int) round($weeks * fake()->numberBetween(3, 20) / 10);
        $rows = [];

        for ($visit = 0; $visit < $visits; $visit++) {
            $checkedInAt = CarbonImmutable::instance(fake()->dateTimeBetween($windowStart, 'now'))
                ->setTime(fake()->randomElement([7, 8, 9, 10, 16, 18, 19, 20]), fake()->numberBetween(0, 59));

            $rows[] = [
                'club_id' => $club->id,
                'user_id' => $user->id,
                'lesson_occurrence_id' => null,
                'subscription_id' => $subscription?->id,
                'strip_card_id' => $subscription ? null : $stripCard?->id,
                'checked_in_by' => null,
                'checked_in_at' => $checkedInAt->toDateTimeString(),
                'created_at' => $checkedInAt->toDateTimeString(),
                'updated_at' => $checkedInAt->toDateTimeString(),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{name: string, day_of_week: int, start_time: string, capacity: int}>  $lessonDefinitions
     * @param  Collection<int, Room>  $rooms
     * @param  Collection<int, Trainer>  $trainers
     */
    protected function seedLessons(Club $club, array $lessonDefinitions, Collection $rooms, Collection $trainers): void
    {
        foreach ($lessonDefinitions as $index => $definition) {
            $lesson = Lesson::create($definition + [
                'club_id' => $club->id,
                'room_id' => $rooms[$index % $rooms->count()]->id,
                'duration_minutes' => 60,
                'starts_on' => now()->toDateString(),
                'active' => true,
            ]);

            $lesson->trainers()->sync([$trainers[$index % $trainers->count()]->id]);

            app(OccurrenceGenerator::class)->generateForLesson($lesson);
        }
    }

    /**
     * Fill upcoming occurrences to a believable level via the real enrollment
     * action, so booked counts and credits stay consistent.
     *
     * @param  Collection<int, array{user: User, joined: CarbonImmutable}>  $activeMembers
     */
    protected function seedEnrollments(Club $club, Collection $activeMembers): void
    {
        $occurrences = LessonOccurrence::query()
            ->where('club_id', $club->id)
            ->whereBetween('starts_at', [now(), now()->addDays(14)])
            ->with('lesson')
            ->get();

        $enrollSingle = app(EnrollSingle::class);

        foreach ($occurrences as $occurrence) {
            $target = (int) floor($occurrence->effectiveCapacity() * fake()->numberBetween(30, 75) / 100);

            foreach ($activeMembers->shuffle()->take($target) as $member) {
                try {
                    $enrollSingle->handle($member['user'], $occurrence);
                } catch (EnrollmentException) {
                    // Full, no credits or already enrolled: fine for demo data.
                }
            }
        }
    }

    protected function randomJoinDate(): CarbonImmutable
    {
        // Linear weights make recent months the most common, so charts trend up.
        $monthsAgo = collect(range(0, 13))
            ->flatMap(fn (int $month) => array_fill(0, 14 - $month, $month))
            ->random();

        return now()->subMonths($monthsAgo)->subDays(fake()->numberBetween(0, 27))->startOfDay();
    }
}
