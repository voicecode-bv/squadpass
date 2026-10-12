<?php

use App\Enums\StaffRole;
use App\Livewire\GlobalSearch;
use App\Models\Club;
use App\Models\Lesson;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create(['name' => 'Padel Club Amsterdam']);
    $this->staff = User::factory()->create();
    $this->club->addStaff($this->staff, StaffRole::Owner);
});

test('a short query returns nothing', function () {
    $this->club->addMember(User::factory()->create(['name' => 'Sanne de Vries']));

    $component = Livewire::actingAs($this->staff)
        ->test(GlobalSearch::class)
        ->set('query', 'S');

    expect($component->instance()->groups)->toBe([]);
});

test('staff find members, lessons and tickets of their own club', function () {
    $member = User::factory()->create(['name' => 'Sanne de Vries']);
    $this->club->addMember($member);
    Lesson::factory()->for($this->club)->create(['name' => 'Sanne haar clinic']);
    Ticket::factory()->create(['club_id' => $this->club->id, 'subject' => 'Sanne kan niet inloggen']);

    $component = Livewire::actingAs($this->staff)
        ->test(GlobalSearch::class)
        ->set('query', 'Sanne');

    $headings = array_column($component->instance()->groups, 'heading');

    expect($headings)->toBe([__('Members'), __('Lessons'), __('Tickets')]);

    $component->assertSee('Sanne de Vries')
        ->assertSee('Sanne haar clinic')
        ->assertSee('Sanne kan niet inloggen');
});

test('results never leak from another club', function () {
    $otherClub = Club::factory()->create(['name' => 'Squash Centrum Utrecht']);
    $otherMember = User::factory()->create(['name' => 'Sanne de Vries']);
    $otherClub->addMember($otherMember);
    Lesson::factory()->for($otherClub)->create(['name' => 'Sanne haar clinic']);

    $component = Livewire::actingAs($this->staff)
        ->test(GlobalSearch::class)
        ->set('query', 'Sanne');

    expect($component->instance()->groups)->toBe([]);

    $component->assertDontSee('Sanne de Vries')
        ->assertDontSee('Sanne haar clinic');
});

test('a ticket can be found by its number', function () {
    $ticket = Ticket::factory()->create(['club_id' => $this->club->id, 'subject' => 'Factuur klopt niet']);

    Livewire::actingAs($this->staff)
        ->test(GlobalSearch::class)
        ->set('query', $ticket->reference())
        ->assertSee('Factuur klopt niet');
});

test('a superadmin searches every club', function () {
    $superadmin = User::factory()->create(['is_superadmin' => true]);
    $otherClub = Club::factory()->create(['name' => 'Squash Centrum Utrecht']);
    $otherClub->addMember(User::factory()->create(['name' => 'Sanne de Vries']));

    Livewire::actingAs($superadmin)
        ->test(GlobalSearch::class)
        ->set('query', 'Sanne')
        ->assertSee('Sanne de Vries');
});

test('a member without staff clubs gets no search results', function () {
    $member = User::factory()->create(['name' => 'Sanne de Vries']);
    $this->club->addMember($member);

    $component = Livewire::actingAs($member)
        ->test(GlobalSearch::class)
        ->set('query', 'Sanne');

    expect($component->instance()->groups)->toBe([]);
});
