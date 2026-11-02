<?php

use App\Enums\StaffRole;
use App\Livewire\Members\Show;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);

    $this->member = User::factory()->create(['name' => 'Detail Lid']);
    $this->membership = $this->club->addMember($this->member);
});

test('staff see the member details with data scoped to that member', function () {
    Subscription::factory()->for($this->club)->active()->create(['user_id' => $this->member->id]);
    Payment::factory()->for($this->club)->paid()->create(['user_id' => $this->member->id, 'amount_cents' => 1234]);
    Payment::factory()->for($this->club)->paid()->create(['amount_cents' => 9999]);
    CheckIn::factory()->create(['club_id' => $this->club->id, 'user_id' => $this->member->id]);

    $this->actingAs($this->owner)
        ->get(route('clubs.members.show', [$this->club, $this->membership]))
        ->assertOk()
        ->assertSee('Detail Lid')
        ->assertSee('12,34')
        ->assertDontSee('99,99')
        ->assertSee(__('Subscriptions & strip cards'));
});

test('the active tab is read from the url and falls back to the membership tab', function () {
    Livewire::actingAs($this->owner)
        ->withQueryParams(['tab' => 'payments'])
        ->test(Show::class, ['club' => $this->club, 'membership' => $this->membership])
        ->assertSet('tab', 'payments');

    Livewire::actingAs($this->owner)
        ->withQueryParams(['tab' => 'nonsense'])
        ->test(Show::class, ['club' => $this->club, 'membership' => $this->membership])
        ->assertSet('tab', 'membership');
});

test('non-staff cannot view member details', function () {
    $this->actingAs($this->member)
        ->get(route('clubs.members.show', [$this->club, $this->membership]))
        ->assertForbidden();
});

test('a membership of another club is not found on this club route', function () {
    $otherClub = Club::factory()->create();
    $otherMembership = $otherClub->addMember(User::factory()->create());

    $this->actingAs($this->owner)
        ->get(route('clubs.members.show', [$this->club, $otherMembership]))
        ->assertNotFound();
});
