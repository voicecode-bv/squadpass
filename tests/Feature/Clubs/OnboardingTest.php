<?php

use App\Enums\StaffRole;
use App\Livewire\Clubs\Index;
use App\Livewire\Clubs\Onboarding;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\StripCardPlan;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->club = Club::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
});

test('creating a club redirects to onboarding', function () {
    $user = User::factory()->create();
    Club::factory()->create()->addStaff($user, StaffRole::Owner);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->set('name', 'Nieuwe Club')
        ->call('createClub')
        ->assertRedirect(route('clubs.onboarding', Club::query()->where('name', 'Nieuwe Club')->firstOrFail()));
});

test('the owner can complete the full onboarding flow', function () {
    Mail::fake();

    $component = Livewire::actingAs($this->owner)
        ->test(Onboarding::class, ['club' => $this->club])
        // Step 1: rooms
        ->set('room_name', 'Baan 1')
        ->call('addRoom')
        ->assertHasNoErrors()
        ->call('next')
        // Step 2: trainers
        ->set('trainer_email', 'trainer@example.com')
        ->call('inviteTrainer')
        ->assertHasNoErrors()
        ->call('next')
        // Step 3: first lesson
        ->set('lesson_name', 'Squash beginners')
        ->set('lesson_room_id', Room::query()->firstOrFail()->id)
        ->call('createLesson')
        ->assertHasNoErrors()
        ->call('next')
        // Step 4: offering
        ->set('plan_name', 'Onbeperkt')
        ->set('plan_price', '59.00')
        ->call('createSubscriptionPlan')
        ->assertHasNoErrors()
        ->set('card_name', '10-strippenkaart')
        ->set('card_price', '75.00')
        ->call('createStripCardPlan')
        ->assertHasNoErrors()
        ->call('next')
        // Step 5: Mollie
        ->set('mollie_api_key', 'test_abc123')
        ->call('saveMollieKey')
        ->assertHasNoErrors()
        ->call('finish')
        ->assertRedirect(route('clubs.members', $this->club));

    $this->club->refresh();
    $lesson = Lesson::query()->firstOrFail();

    expect($this->club->isOnboarded())->toBeTrue()
        ->and($this->club->mollie_api_key)->toBe('test_abc123')
        ->and(Room::query()->where('club_id', $this->club->id)->count())->toBe(1)
        ->and(ClubInvitation::query()->where('email', 'trainer@example.com')->where('club_id', $this->club->id)->exists())->toBeTrue()
        ->and($lesson->room_id)->toBe(Room::query()->firstOrFail()->id)
        ->and($lesson->occurrences()->count())->toBeGreaterThan(0)
        ->and(SubscriptionPlan::query()->where('price_cents', 5900)->exists())->toBeTrue()
        ->and(StripCardPlan::query()->where('price_cents', 7500)->exists())->toBeTrue();
});

test('the onboarding lesson step supports multiple weekly time slots', function () {
    Livewire::actingAs($this->owner)
        ->test(Onboarding::class, ['club' => $this->club])
        ->set('lesson_name', 'Padel training')
        ->set('lesson_schedules', [
            ['day_of_week' => 1, 'start_time' => '19:00', 'duration_minutes' => 60],
            ['day_of_week' => 4, 'start_time' => '20:30', 'duration_minutes' => 90],
        ])
        ->call('createLesson')
        ->assertHasNoErrors();

    $lessons = Lesson::query()->where('name', 'Padel training')->orderBy('day_of_week')->get();

    expect($lessons)->toHaveCount(2)
        ->and($lessons[0]->day_of_week)->toBe(1)
        ->and($lessons[1]->day_of_week)->toBe(4)
        ->and($lessons[1]->duration_minutes)->toBe(90);

    $lessons->each(fn (Lesson $lesson) => expect($lesson->occurrences()->count())->toBeGreaterThan(0));
});

test('every step can be skipped and finishing still marks the club onboarded', function () {
    Livewire::actingAs($this->owner)
        ->test(Onboarding::class, ['club' => $this->club])
        ->call('next')
        ->call('next')
        ->call('next')
        ->call('next')
        ->call('finish')
        ->assertRedirect(route('clubs.members', $this->club));

    expect($this->club->refresh()->isOnboarded())->toBeTrue();
});

test('an admin cannot access onboarding', function () {
    $admin = User::factory()->create();
    $this->club->addStaff($admin, StaffRole::Admin);

    Livewire::actingAs($admin)
        ->test(Onboarding::class, ['club' => $this->club])
        ->assertForbidden();
});

test('a duplicate room name shows a validation error', function () {
    Room::factory()->for($this->club)->create(['name' => 'Baan 1']);

    Livewire::actingAs($this->owner)
        ->test(Onboarding::class, ['club' => $this->club])
        ->set('room_name', 'Baan 1')
        ->call('addRoom')
        ->assertHasErrors(['room_name']);
});

test('the clubs index links to onboarding until it is finished', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->assertSee(__('Finish setup'))
        ->assertSee(route('clubs.onboarding', $this->club));

    $this->club->update(['onboarded_at' => now()]);

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->assertDontSee(__('Finish setup'))
        ->assertSee(route('clubs.dashboard', $this->club));
});
