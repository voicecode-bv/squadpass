<?php

use App\Enums\EnrollmentType;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Livewire\Dashboard;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\StripCard;
use App\Models\Subscription;
use App\Models\Trainer;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('a staff member sees their clubs with active member counts', function () {
    $user = User::factory()->create();
    $club = Club::factory()->create(['name' => 'Padel Club Oost']);
    $club->addStaff($user, StaffRole::Owner);
    $club->addMember(User::factory()->create());

    $otherClub = Club::factory()->create(['name' => 'Andermans Club']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Padel Club Oost')
        ->assertSee(trans_choice(':count active member|:count active members', 1))
        ->assertDontSee('Andermans Club');
});

test('the my clubs navigation is only shown to staff', function () {
    $club = Club::factory()->create();

    $member = User::factory()->create();
    $club->addMember($member);

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertDontSee(__('My clubs'));

    $owner = User::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertSee(__('My clubs'));
});

test('a member sees only their own upcoming enrollments', function () {
    $club = Club::factory()->create();
    $member = User::factory()->create();
    $club->addMember($member);

    $lesson = Lesson::factory()->for($club)->create(['name' => 'Eigen boeking']);
    $occurrence = LessonOccurrence::factory()->for($lesson)->create(['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
    LessonEnrollment::factory()->create([
        'club_id' => $club->id,
        'user_id' => $member->id,
        'lesson_id' => $lesson->id,
        'lesson_occurrence_id' => $occurrence->id,
        'type' => EnrollmentType::Single,
    ]);

    $otherLesson = Lesson::factory()->for($club)->create(['name' => 'Andermans boeking']);
    $otherOccurrence = LessonOccurrence::factory()->for($otherLesson)->create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);
    LessonEnrollment::factory()->create([
        'club_id' => $club->id,
        'lesson_id' => $otherLesson->id,
        'lesson_occurrence_id' => $otherOccurrence->id,
        'type' => EnrollmentType::Single,
    ]);

    Livewire::actingAs($member)
        ->test(Dashboard::class)
        ->assertSee('Eigen boeking')
        ->assertDontSee('Andermans boeking');
});

test('a trainer sees the upcoming lessons they teach', function () {
    $club = Club::factory()->create();
    $trainerUser = User::factory()->create();
    $trainer = Trainer::factory()->for($club)->create(['user_id' => $trainerUser->id]);

    $lesson = Lesson::factory()->for($club)->create(['name' => 'Spinning marathon']);
    $lesson->trainers()->attach($trainer);
    LessonOccurrence::factory()->for($lesson)->create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);

    Livewire::actingAs($trainerUser)
        ->test(Dashboard::class)
        ->assertSee('Spinning marathon');
});

test('new members per month counts staff clubs only', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    Membership::factory()->count(2)->for($club)->create(['joined_at' => now()->startOfMonth()->addDay()->toDateString()]);
    Membership::factory()->for($club)->create(['joined_at' => now()->startOfMonth()->subMonth()->addDays(3)->toDateString()]);
    Membership::factory()->create(['joined_at' => now()->startOfMonth()->addDay()->toDateString()]);

    $component = Livewire::actingAs($owner)->test(Dashboard::class);
    $series = $component->instance()->newMembersPerMonth;

    expect($series['values'][11])->toBe(2)
        ->and($series['values'][10])->toBe(1)
        ->and($component->instance()->newMembersLastMonth)->toBe(1);
});

test('active members per month measures month-end snapshots', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    Membership::factory()->for($club)->create(['joined_at' => now()->startOfMonth()->subMonths(5)->addDays(3)->toDateString()]);
    Membership::factory()->for($club)->create([
        'joined_at' => now()->startOfMonth()->subMonths(3)->addDay()->toDateString(),
        'ended_at' => now()->startOfMonth()->subMonth()->addDays(2)->toDateString(),
    ]);

    $series = Livewire::actingAs($owner)->test(Dashboard::class)->instance()->activeMembersPerMonth;

    expect($series['values'][3])->toBe(0)
        ->and($series['values'][9])->toBe(2)
        ->and($series['values'][11])->toBe(1);
});

test('revenue per month sums paid payments of staff clubs only', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    Payment::factory()->for($club)->paid()->create(['amount_cents' => 5000, 'paid_at' => now()->startOfMonth()->addDay()]);
    Payment::factory()->for($club)->paid()->create(['amount_cents' => 2500, 'paid_at' => now()->startOfMonth()->subMonth()->addDays(3)]);
    Payment::factory()->for($club)->create(['amount_cents' => 9999]);
    Payment::factory()->paid()->create(['amount_cents' => 7777, 'paid_at' => now()->startOfMonth()->addDay()]);

    $series = Livewire::actingAs($owner)->test(Dashboard::class)->instance()->revenuePerMonth;

    expect($series['values'][11])->toBe(5000)
        ->and($series['values'][10])->toBe(2500);
});

test('the dashboard warns staff about failed charges in their clubs only', function () {
    $owner = User::factory()->create();
    $club = Club::factory()->create();
    $club->addStaff($owner, StaffRole::Owner);

    $subscription = Subscription::factory()->for($club)->create();
    Payment::factory()->for($club)->create([
        'user_id' => $subscription->user_id,
        'payable_type' => Subscription::class,
        'payable_id' => $subscription->id,
        'status' => PaymentStatus::Failed,
        'metadata' => ['purpose' => 'subscription_recurring'],
    ]);

    $otherClubSubscription = Subscription::factory()->create();
    Payment::factory()->create([
        'club_id' => $otherClubSubscription->club_id,
        'user_id' => $otherClubSubscription->user_id,
        'payable_type' => Subscription::class,
        'payable_id' => $otherClubSubscription->id,
        'status' => PaymentStatus::Failed,
        'metadata' => ['purpose' => 'subscription_recurring'],
    ]);

    $component = Livewire::actingAs($owner)->test(Dashboard::class);

    expect($component->instance()->failedChargesByClub->sum())->toBe(1);
});

test('remaining strips only count usable cards', function () {
    $club = Club::factory()->create();
    $member = User::factory()->create();
    $club->addMember($member);

    StripCard::factory()->create(['club_id' => $club->id, 'user_id' => $member->id, 'strips_remaining' => 7]);
    StripCard::factory()->create(['club_id' => $club->id, 'user_id' => $member->id, 'strips_remaining' => 5, 'expires_at' => now()->subDay()]);
    StripCard::factory()->empty()->create(['club_id' => $club->id, 'user_id' => $member->id]);

    $component = Livewire::actingAs($member)->test(Dashboard::class);

    expect($component->instance()->stripsRemaining)->toBe(7);
});
