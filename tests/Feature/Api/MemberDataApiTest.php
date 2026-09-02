<?php

use App\Models\CheckIn;
use App\Models\Club;
use App\Models\StripCard;
use App\Models\Subscription;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    Sanctum::actingAs($this->member);
});

test('clubs lists the member clubs with membership info', function () {
    Club::factory()->create(['name' => 'Andere club']);

    $this->getJson('/api/v1/clubs')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->club->id)
        ->assertJsonPath('data.0.membership_number', 1);
});

test('strip cards are scoped to the member', function () {
    StripCard::factory()->create(['club_id' => $this->club->id, 'user_id' => $this->member->id, 'strips_remaining' => 7]);
    StripCard::factory()->create(['club_id' => $this->club->id]);

    $this->getJson('/api/v1/strip-cards')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.strips_remaining', 7)
        ->assertJsonPath('data.0.is_usable', true);
});

test('subscriptions are scoped to the member', function () {
    Subscription::factory()->for($this->club)->active()->create(['user_id' => $this->member->id]);
    Subscription::factory()->for($this->club)->active()->create();

    $this->getJson('/api/v1/subscriptions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'active');
});

test('visits are scoped to the member and paginated', function () {
    CheckIn::factory()->create(['club_id' => $this->club->id, 'user_id' => $this->member->id]);
    CheckIn::factory()->create(['club_id' => $this->club->id]);

    $this->getJson('/api/v1/visits')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
});
