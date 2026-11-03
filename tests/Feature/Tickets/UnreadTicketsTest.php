<?php

use App\Enums\StaffRole;
use App\Livewire\Portal\TicketShow;
use App\Livewire\Tickets\Show;
use App\Models\Club;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->club = Club::factory()->create();
    $this->owner = User::factory()->create();
    $this->club->addStaff($this->owner, StaffRole::Owner);
    $this->member = User::factory()->create();
    $this->club->addMember($this->member);

    $this->ticket = Ticket::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $this->member->id,
        'subject' => 'Deur van de kleedkamer klemt',
    ]);
});

test('a message from the member is unread for the club staff until they open the ticket', function () {
    TicketMessage::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $this->member->id]);

    $this->actingAs($this->owner)->get(route('clubs.members', $this->club))
        ->assertOk()
        ->assertSeeHtml('data-test="unread-tickets-badge"')
        ->assertSee('Deur van de kleedkamer klemt')
        ->assertSeeHtml('href="'.route('clubs.tickets.show', [$this->club, $this->ticket]).'"');

    Livewire::actingAs($this->owner)->test(Show::class, ['club' => $this->club, 'ticket' => $this->ticket]);

    expect(Ticket::query()->unreadFor($this->owner)->exists())->toBeFalse();
});

test('a staff reply is unread for the member and opens in the portal', function () {
    $this->travel(-1)->minutes();
    TicketMessage::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $this->member->id]);
    $this->travelBack();
    TicketMessage::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $this->owner->id]);

    $this->actingAs($this->member)->get(route('portal.tickets'))
        ->assertOk()
        ->assertSeeHtml('href="'.route('portal.tickets.show', $this->ticket).'"');

    Livewire::actingAs($this->member)->test(TicketShow::class, ['ticket' => $this->ticket]);

    expect(Ticket::query()->unreadFor($this->member)->exists())->toBeFalse();
});

test('a new message after reading makes the ticket unread again', function () {
    TicketMessage::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $this->member->id]);
    $this->ticket->markReadBy($this->owner);
    $this->travel(1)->minutes();

    TicketMessage::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $this->member->id]);

    expect(Ticket::query()->unreadFor($this->owner)->pluck('id')->all())->toBe([$this->ticket->id]);
});

test('your own message is never unread for you', function () {
    TicketMessage::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $this->member->id]);

    expect(Ticket::query()->unreadFor($this->member)->exists())->toBeFalse();
});

test('staff only see unread tickets of the clubs they manage', function () {
    $otherTicket = Ticket::factory()->create();
    TicketMessage::factory()->create(['ticket_id' => $otherTicket->id, 'user_id' => $otherTicket->user_id]);

    expect(Ticket::query()->unreadFor($this->owner)->exists())->toBeFalse();
});

test('tickets from before unread tracking are not unread', function () {
    TicketMessage::factory()->create(['ticket_id' => $this->ticket->id, 'user_id' => $this->member->id]);
    Ticket::query()->whereKey($this->ticket->id)->update(['last_message_at' => null, 'last_message_by' => null]);

    $this->actingAs($this->owner)->get(route('clubs.members', $this->club))
        ->assertOk()
        ->assertDontSeeHtml('data-test="unread-tickets-badge"')
        ->assertSee(__('No unread messages'));
});
